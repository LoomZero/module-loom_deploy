<?php

namespace Drupal\loom_deploy\Test;

use Drupal\loom_deploy\Deploy\DeployInterface;
use Drupal\loom_deploy\Exception\DeployErrorException;
use Drupal\loom_deploy\Manager\DeployManager;
use Exception;

class DeployTest implements DeployInterface {

  public function name(): string {
    return 'Deploy Test';
  }

  public function check(DeployManager $manager): bool {
    return TRUE;
  }

  public function execute(DeployManager $manager) {
    $manager->entityCreate('test', 'node', ['type' => 'article', 'title' => 'Cool', 'field_number' => 30]);
  }

}
