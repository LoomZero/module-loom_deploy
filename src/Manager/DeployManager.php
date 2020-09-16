<?php

namespace Drupal\loom_deploy\Manager;

use Drupal;
use Drupal\Console\Core\Style\DrupalStyle;
use Drupal\Core\Url;
use Drupal\loom_deploy\Command\DeployCommand;
use Drupal\loom_deploy\Deploy\DeployInterface;
use Drupal\loom_deploy\Exception\DeployCollectionException;
use Drupal\loom_deploy\Exception\DeployErrorException;
use Throwable;
use function get_class;

class DeployManager {

  /** @var DeployInterface[][] */
  private $services = [];
  /** @var DeployInterface[] */
  private $sorted = NULL;
  /** @var DeployCommand */
  private $command = NULL;

  /** @var DeployEntityManager */
  private $entity = NULL;
  /** @var DeployFileManager */
  private $file = NULL;

  private $exceptions = 0;
  private $executed = 0;

  private $current = NULL;
  private $cache = [];

  public function entity(): DeployEntityManager {
    if ($this->entity === NULL) {
      $this->entity = new DeployEntityManager($this);
    }
    return $this->entity;
  }

  public function file(): DeployFileManager {
    if ($this->file === NULL) {
      $this->file = new DeployFileManager($this);
    }
    return $this->file;
  }

  public function addService(DeployInterface $service, $priority = 0): DeployManager {
    $this->services[$priority][] = $service;
    $this->sorted = NULL;
    return $this;
  }

  /**
   * @return DeployInterface[]
   */
  public function getDeploys(): array {
    if ($this->sorted === NULL) {
      $this->sorted = [];
      ksort($this->services);
      foreach ($this->services as $services) {
        foreach ($services as $service) {
          $this->sorted[] = $service;
        }
      }
    }
    return $this->sorted;
  }

  public function getIo(): ?DrupalStyle {
    return $this->command->getIo();
  }

  public function getModule(DeployInterface $object = NULL): ?string {
    if ($object === NULL) {
      $object = $this->current['deploy'];
    }

    $exploded = explode('\\', get_class($object));
    if ($exploded[0] === 'Drupal') {
      return $exploded[1];
    }
    return NULL;
  }

  public function execute(DeployCommand $command) {
    $this->command = $command;
    foreach ($this->getDeploys() as $deploy) {
      $this->getIo()->section('Execute ' . $deploy->name());
      if ($deploy->check($this)) {
        try {
          $this->current = [
            'deploy' => $deploy,
          ];
          $this->executed++;
          $deploy->execute($this);
        } catch (Throwable $e) {
          if ($e instanceof DeployErrorException) {
            $this->doError($e);
          } else if ($e instanceof DeployCollectionException) {
            foreach ($e->getExceptions() as $exception) {
              $this->doError($exception);
            }
          } else {
            $this->doError("Exception by executing '" . $deploy->name() . "' deploy script of module " . $this->getModule($deploy) . ".", $e);
          }
        }

        if (empty($this->current['errors'])) {
          $this->success('Success deploy [deploy]', [
            'deploy' => $deploy->name(),
          ]);
        } else {
          foreach ($this->current['errors'] as $error) {
            $this->exceptions++;
            Drupal::logger('loom_deploy')->error($error->getFullMessage());
            $this->getIo()->errorLite('Error: ' . $error->getMessage());
          }
        }
      }
    }

    if ($this->exceptions > 0) {
      $this->getIo()->error([
        'Result:',
        'Executed ' . $this->executed  . ' deploy scripts with ' . $this->exceptions . ' error`s',
        'More infos on ' . Url::fromRoute('dblog.overview')->setAbsolute()->toString(),
      ]);
    } else {
      $this->getIo()->success([
        'Result:',
        'Executed ' . $this->executed . ' deploy scripts.',
      ]);
    }
  }

  public function setError(string $title, $more = NULL, Throwable $exception = NULL, bool $throw = TRUE): DeployErrorException {
    $exception = new DeployErrorException($title, $more, $exception);
    if ($throw) {
      throw $exception;
    }
    return $exception;
  }

  private function doError($title, Throwable $exception = NULL) {
    if ($title instanceof Throwable) {
      $this->current['errors'][] = $title;
    } else {
      $this->current['errors'][] = new DeployErrorException($title, $exception);
    }
  }

  /**
   * @param string $ident
   * @param bool $edit if edit is true do nothing, it is edited
   *
   * @return array|null
   */
  public function checkDeployIdent(string $ident, bool $edit = FALSE): ?array {
    if (!isset($this->cache[$ident])) {
      $value = Drupal::state()->get('loom_deploy.' . $ident);
      if (is_array($value) && ($edit && !$value['edit'] || !$edit)) {
        $this->success('Check ident [ident] success', ['ident' => $ident]);
        $this->cache[$ident] = $value;
      } else {
        $this->error('Check ident [ident] failed', ['ident' => $ident]);
        $this->cache[$ident] = TRUE;
      }
    }
    return ($this->cache[$ident] === TRUE ? NULL : $this->cache[$ident]);
  }

  public function setDeployIdent(string $ident, string $type, $value, bool $edit = FALSE) {
    $this->log('Set deploy state [type] for [ident] value: [value]', [
      'type' => $type,
      'ident' => $ident,
      'value' => print_r($value, TRUE),
    ]);
    $state = [
      'type' => $type,
      'value' => $value,
      'ident' => $ident,
      'edit' => $edit,
      'class' => get_class($this->current['deploy']),
      'module' => $this->getModule($this->current['deploy']),
      'name' => $this->current['deploy']->name(),
    ];
    Drupal::state()->set('loom_deploy.' . $ident, $state);
    $this->cache[$ident] = $state;
  }

  public function log(string $message, array $placeholders = []) {
    foreach ($placeholders as $placeholder => $value) {
      $message = str_replace('[' . $placeholder . ']', '<fg=green>"' . $value . '"</>', $message);
    }
    $this->getIo()->writeln($message);
  }

  public function success(string $message, array $placeholders = []) {
    foreach ($placeholders as $placeholder => $value) {
      $message = str_replace('[' . $placeholder . ']', '<fg=green>"' . $value . '"</>', $message);
    }
    $this->getIo()->successLite($message);
  }

  public function warn(string $message, array $placeholders = []) {
    foreach ($placeholders as $placeholder => $value) {
      $message = str_replace('[' . $placeholder . ']', '<fg=green>"' . $value . '"</>', $message);
    }
    $this->getIo()->warningLite($message);
  }

  public function error(string $message, array $placeholders = []) {
    foreach ($placeholders as $placeholder => $value) {
      $message = str_replace('[' . $placeholder . ']', '<fg=green>"' . $value . '"</>', $message);
    }
    $this->getIo()->errorLite($message);
  }

}
