<?php

namespace Drupal\loom_deploy\Manager;

use Drupal;
use Drupal\Console\Core\Style\DrupalStyle;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\loom_deploy\Command\DeployCommand;
use Drupal\loom_deploy\Deploy\DeployInterface;
use Drupal\loom_deploy\Exception\DeployCollectionException;
use Drupal\loom_deploy\Exception\DeployErrorException;
use Throwable;

class DeployManager {

  /** @var DeployInterface[][] */
  private $services = [];
  /** @var DeployInterface[] */
  private $sorted = NULL;
  /** @var DeployCommand */
  private $command = NULL;
  /** @var FileSystemInterface */
  private $fs = NULL;
  /** @var EntityTypeManagerInterface */
  private $etm = NULL;

  private $exceptions = 0;
  private $executed = 0;

  private $current = NULL;

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

  public function fs(): FileSystemInterface {
    if ($this->fs === NULL) {
      $this->fs = Drupal::service('file_system');
    }
    return $this->fs;
  }

  public function entityTypeManager(): EntityTypeManagerInterface {
    if ($this->etm === NULL) {
      $this->etm = Drupal::entityTypeManager();
    }
    return $this->etm;
  }

  public function getModule(DeployInterface $object): ?string {
    $exploded = explode('\\', get_class($object));
    if ($exploded[0] === 'Drupal') {
      return $exploded[1];
    }
    return NULL;
  }

  public function execute(DeployCommand $command) {
    $this->command = $command;
    foreach ($this->getDeploys() as $deploy) {
      $this->getModule($deploy);
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
          $this->getIo()->successLite('Success');
        } else {
          foreach ($this->current['errors'] as $error) {
            $this->exceptions++;
            Drupal::logger('loom_deploy')->error($error->getFullMessage());
            $this->getIo()->errorLite('Error: ' . $error->getMessage());
          }
        }
      }
    }

    $this->getIo()->section('Result:');
    if ($this->exceptions > 0) {
      $this->getIo()->errorLite('Executed ' . $this->executed  . ' deploy scripts with ' . $this->exceptions . ' error`s');
    } else {
      $this->getIo()->successLite('Executed ' . $this->executed . ' deploy scripts.');
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

  public function entityCreate(string $ident, string $type, array $fields = []): ?EntityInterface {
    $entity = $this->entityPrepare($ident, $type, $fields);
    if ($entity !== NULL) {
      return $this->entitySave($ident, $entity);
    } else {
      return NULL;
    }
  }

  public function entityPrepare(string $ident, string $type, array $fields = []): ?EntityInterface {
    $this->log('Prepare entity [type] with identifier [ident]', [
      'type' => $type,
      'ident' => $ident,
    ]);
    if (!$this->checkDeployIdent($ident)) {
      return $this->entityTypeManager()->getStorage($type)->create($fields);
    } else {
      return NULL;
    }
  }

  public function entitySave(string $ident, EntityInterface $entity): EntityInterface {
    $this->log('Save entity with identifier [ident].', [
      'ident' => $ident,
    ]);
    if ($entity instanceof FieldableEntityInterface) {
      $error = [];
      foreach ($entity->validate() as $violation) {
        $error[] = 'Field ' . $violation->getPropertyPath() . ': ' . $violation->getMessage();
      }
      if (count($error)) {
        $this->setError('Error on create ' . $entity->bundle() . ' ' . $ident, $error);
      }
    }
    $entity->save();
    $this->setDeployIdent($ident, 'entity', [
      'id' => $entity->id(),
      'type' => $entity->getEntityTypeId(),
    ]);
    return $entity;
  }

  /**
   * @param string $from Glob support
   * @param string $to Glob support
   */
  public function copyDir(string $from, string $to) {
    $this->log('Copy directory [from] to [to]', [
      'from' => $from,
      'to' => $to,
    ]);

    if (!$this->fs()->prepareDirectory($to)) {
      $this->log('Create directory [to]', ['to' => $to]);
      if ($this->fs()->mkdir($to)) {
        $this->fs()->prepareDirectory($to);
      } else {
        $this->getIo()->errorLite('Directory can not be created "' . $to . '"');
      }
    }

    $files = glob($from);
    foreach ($files as $file) {
      $this->copyFile($file, $to . '/' . basename($file));
    }
  }

  /**
   * @param $from
   * @param $to
   */
  public function copyFile($from, $to) {
    if (!file_exists($to)) {
      $this->log('Copy [from] to [to] ...', [
        'from' => $from,
        'to' => $to,
      ]);
      $data = file_get_contents($from);
      $file = file_save_data($data, $to, FileSystemInterface::EXISTS_REPLACE);
      if ($file) {
        $file->setPermanent();
        $file->save();
      } else {
        $this->getIo()->errorLite('Error by copy file "' . $from . '"');
      }
    } else {
      $this->log('Already copied [to]', ['to' => $to]);
    }
  }

  public function checkDeployIdent(string $ident): bool {
    $value = Drupal::state()->get('loom_deploy.' . $ident) !== NULL;
    if ($value) {
      $this->getIo()->successLite('Check "' . $ident . '" success');
    } else {
      $this->getIo()->errorLite('Check "' . $ident . '" failed');
    }
    return $value;
  }

  public function setDeployIdent(string $ident, string $type, $value) {
    $this->log('Set deploy state [type] for [ident] value: [value]', [
      'type' => $type,
      'ident' => $ident,
      'value' => print_r($value, TRUE),
    ]);
    Drupal::state()->set('loom_deploy.' . $ident, [
      'type' => $type,
      'value' => $value,
    ]);
  }

  public function log($message, array $placeholders = []) {
    foreach ($placeholders as $placeholder => $value) {
      $message = str_replace('[' . $placeholder . ']', '<fg=green>"' . $value . '"</>', $message);
    }
    $this->getIo()->writeln($message);
  }

}
