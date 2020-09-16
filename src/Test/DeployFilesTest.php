<?php

namespace Drupal\loom_deploy\Test;

use Drupal\loom_deploy\Deploy\DeployInterface;
use Drupal\loom_deploy\Exception\DeployErrorException;
use Drupal\loom_deploy\Manager\DeployManager;
use Exception;

class DeployFilesTest implements DeployInterface {

  public function name(): string {
    return 'Deploy Files Test';
  }

  public function check(DeployManager $manager): bool {
    return TRUE;
  }

  public function execute(DeployManager $manager) {
    $manager->file()->copyFile('file', 'module:/README.md', 'public://hallo_2.md');
  }

}
