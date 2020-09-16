<?php

namespace Drupal\loom_deploy\Manager;

use Drupal;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\loom_deploy\Exception\DeployException;

class DeployFileManager {

  /** @var DeployManager */
  private $manager = NULL;

  /** @var FileSystemInterface */
  private $fs = NULL;
  /** @var ModuleHandlerInterface */
  private $moduleHandler = NULL;

  public function __construct(DeployManager $manager) {
    $this->manager = $manager;
  }

  public function fs(): FileSystemInterface {
    if ($this->fs === NULL) {
      $this->fs = Drupal::service('file_system');
    }
    return $this->fs;
  }

  public function moduleHandler(): ModuleHandlerInterface {
    if ($this->moduleHandler === NULL) {
      $this->moduleHandler = Drupal::service('module_handler');
    }
    return $this->moduleHandler;
  }

  private function preparePath($path) {
    if (is_string($path)) {
      if (strpos($path, 'module:') === 0) {
        return $this->moduleHandler()->getModule($this->manager->getModule())->getPath() . substr($path, 7);
      }
    }
    return $path;
  }

  /**
   * @param string $from Glob support
   * @param string $to
   */
  public function copyDir(string $from, string $to) {
    $this->manager->log('Copy directory [from] to [to] ...', [
      'from' => $from,
      'to' => $to,
    ]);

    if (!$this->fs()->prepareDirectory($to)) {
      $this->manager->log('Create directory [to]', ['to' => $to]);
      if ($this->fs()->mkdir($to)) {
        $this->fs()->prepareDirectory($to);
      } else {
        $this->manager->error('Directory can not be created [to]', [
          'to' => $to,
        ]);
      }
    }

    $files = glob($from);
    foreach ($files as $file) {
      $this->copyFile($file, $to . '/' . basename($file));
    }
    $this->manager->success('Copied directory [from] to [to]', [
      'from' => $from,
      'to' => $to,
    ]);
  }

  /**
   * @param string $ident
   * @param string $from
   * @param string $to
   */
  public function copyFile(string $ident, string $from, string $to) {
    $this->doCopyFile($from, $to, $ident);
  }

  private function doCopyFile(string $from, string $to, string $ident = NULL) {
    $to = $this->preparePath($to);
    $from = $this->preparePath($from);

    $state = $ident === NULL ? FALSE : $this->manager->checkDeployIdent($ident);
    $exist_from = file_exists($from);
    $exist_to = file_exists($to);

    if ($state) {
      return $this->manager->entity()->entityTypeManager()->getStorage($state['value']['type'])->load($state['value']['id']);
    }

    if (!$exist_from) {
      $this->manager->setError('The file "' . $from . '" does not exist.', new DeployException());
    }

    if ($exist_to) {
      if ($ident !== NULL) {
        $file = $this->manager->entity()->prepare($ident, 'file', [
          'uid' => 1,
          'filename' => $this->fs()->basename($to),
          'uri' => $to,
          'status' => 1,
        ], ['uri']);
        if (!$file->isNew()) return $file;
      } else {
        $this->manager->log('The file [to] already exist so create file entity', ['to' => $to]);
        $file = File::create([
          'uid' => 1,
          'filename' => $this->fs()->basename($to),
          'uri' => $to,
          'status' => 1,
        ]);
      }
    } else {
      $data = file_get_contents($from);
      $file = file_save_data($data, $to, FileSystemInterface::EXISTS_REPLACE);
      if ($file) {
        $file->setPermanent();
      } else {
        $this->manager->setError('Error by copy file "' . $from .'"');
      }
    }

    if ($ident === NULL) {
      return $file;
    } else {
      return $this->manager->entity()->save($ident, $file);
    }
  }

}
