<?php

namespace Drupal\loom_deploy\Deploy;

use Drupal\loom_deploy\Manager\DeployManager;

interface DeployInterface {

  public function name(): string;

  public function check(DeployManager $manager): bool;

  public function execute(DeployManager $manager);

}
