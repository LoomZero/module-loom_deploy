<?php

namespace Drupal\loom_deploy\Test;

use Drupal\loom_deploy\Deploy\DeployInterface;
use Drupal\loom_deploy\Exception\DeployErrorException;
use Drupal\loom_deploy\Manager\DeployManager;
use Exception;

class DeployEntityTest implements DeployInterface {

  public function name(): string {
    return 'Deploy Entity Test';
  }

  public function check(DeployManager $manager): bool {
    return TRUE;
  }

  public function execute(DeployManager $manager) {
    $manager->entity()->create('test', 'node', ['type' => 'page', 'title' => 'Test', 'field_number' => 15], ['title']);
    $manager->entity()->create('test_2', 'node', ['type' => 'page', 'title' => 'Test 2', 'field_number' => 10], ['field_number']);
  }

}
