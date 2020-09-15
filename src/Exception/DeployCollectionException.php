<?php

namespace Drupal\loom_deploy\Exception;

class DeployCollectionException extends DeployException {

  private $exceptions = [];

  public function __construct(DeployErrorException ...$exceptions) {
    parent::__construct('Collection exception');
    $this->exceptions = $exceptions;
  }

  /**
   * @return DeployErrorException[]
   */
  public function getExceptions(): array {
    return $this->exceptions;
  }

  public function addException(DeployErrorException $exception): DeployCollectionException {
    $this->exceptions[] = $exception;
    return $this;
  }

}
