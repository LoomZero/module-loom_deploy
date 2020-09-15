<?php

namespace Drupal\loom_deploy\Command;

use Drupal;
use Drupal\loom_deploy\Manager\DeployManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Drupal\Console\Core\Command\ContainerAwareCommand;
use Drupal\Console\Annotations\DrupalCommand;

/**
 * Class DeployCommand.
 *
 * @DrupalCommand (
 *     extension="loom_deploy",
 *     extensionType="module"
 * )
 */
class DeployCommand extends ContainerAwareCommand {

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('loom:deploy')
      ->setDescription('LOOM Deploy');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    /** @var DeployManager $manager */
    $manager = Drupal::service('loom.deploy.manager');

    $manager->execute($this);
  }

}
