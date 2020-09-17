<?php

namespace Drupal\loom_deploy\Test;

use Drupal\loom_deploy\Deploy\DeployInterface;
use Drupal\loom_deploy\Exception\DeployErrorException;
use Drupal\loom_deploy\Manager\DeployManager;
use Exception;

class DeployStateTest implements DeployInterface {

  public function name(): string {
    return 'Deploy State Test';
  }

  public function check(DeployManager $manager): bool {
    return TRUE;
  }

  public function execute(DeployManager $manager) {
    $manager->state()->addState('hallo', 'my_state', ['value' => 'test']);
  }

}
