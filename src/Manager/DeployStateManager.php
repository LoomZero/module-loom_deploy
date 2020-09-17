<?php

namespace Drupal\loom_deploy\Manager;

use Drupal;

class DeployStateManager {

  /** @var DeployManager */
  private $manager = NULL;

  public function __construct(DeployManager $manager) {
    $this->manager = $manager;
  }

  public function addState(string $ident, string $state, $value) {
    $check = $this->manager->checkDeployIdent($ident, TRUE);
    if ($check === NULL) {
      $current = Drupal::state()->get($state);
      if ($current === NULL) {
        Drupal::state()->set($state, $value);
        $this->manager->setDeployIdent($ident, 'state', [
          'state' => $state,
          'value' => $value,
        ]);
        return $value;
      } else {
        $this->manager->log('The state [state] already exist so bind it to ident [ident]', [
          'state' => $state,
          'ident' => $ident,
        ]);
        $this->manager->setDeployIdent($ident, 'state', [
          'state' => $state,
          'value' => $current,
        ]);
        return $current;
      }
    }
    return $value;
  }

}
