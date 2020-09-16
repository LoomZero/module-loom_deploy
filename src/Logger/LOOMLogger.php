<?php

namespace Drupal\loom_deploy\Logger;

use Drupal;
use Drupal\Console\Core\Style\DrupalStyle;
use Psr\Log\LoggerInterface;

class LOOMLogger {

  public function __construct(string $logger) {
    $this->logger = Drupal::logger($logger);
  }

  /** @var LoggerInterface */
  protected $logger = NULL;
  /** @var DrupalStyle */
  protected $io = NULL;

  public function setIO(DrupalStyle $io): LOOMLogger {
    $this->io = $io;
    return $this;
  }

  public function getFullMessage(): string {
    if ($this->error !== NULL) {
      if (empty($this->error->xdebug_message)) {
        $messages = [];
        $messages[] = get_class($this->error) . ': ' . $this->error->getMessage() . ' in ' . $this->error->getFile() . ' on line ' . $this->error->getLine() . "\n\nCall Stack:";
        $messages[] = $this->error->getTraceAsString();
        $exception = implode("\n", $messages);
      } else {
        $exception = $this->error->xdebug_message;
      }
    }

    if (is_array($this->more)) {
      $more = implode("\n", $this->more);
    } else {
      $more = $this->more;
    }

    return nl2br('<details><summary>' . $this->title . '</summary>' . ($more ?? '') . ($exception ?? '') . '</details>');
  }

  protected function prepare($message, array $placeholders = []): array {
    if (is_string($message)) {
      $message = [$message];
    }
    foreach ($placeholders as $placeholder => $value) {
      foreach ($message as $index => $mes) {
        $subject = '"' . $value . '"';
        if ($this->io !== NULL) {
          $subject = '<fg=green>' . $subject . '</>';
        }
        $message[$index] = str_replace('[' . $placeholder . ']', $subject, $mes);
      }
    }
    return $message;
  }

  public function log($message, array $placeholders = []) {
    $message = $this->prepare($message, $placeholders);
    if ($this->io === NULL) {
      Drupal::messenger()->addStatus($message);
    } else {
      $this->io->writeln($message);
    }
  }

  public function warn(string $message, array $placeholders = []) {
    $message = $this->prepare($message, $placeholders);
    if ($this->io === NULL) {
      Drupal::messenger()->addStatus($message);
      $this->logger->error('Warn: ' . $message);
    } else {
      $this->io->warningLite($message);
    }
  }

  public function error(string $message, array $placeholders = []) {
    $message = $this->prepare($message, $placeholders);
    if ($this->io === NULL) {
      Drupal::messenger()->addError($message);
    } else {
      $this->io->errorLite($message);
    }
  }

  public function failed(string $message, array $placeholders = []) {
    $message = $this->prepare($message, $placeholders);
    if ($this->io === NULL) {
      Drupal::messenger()->addError($message);
    } else {
      $this->io->error($message);
    }
  }

  public function success(string $message, array $placeholders = []) {
    $message = $this->prepare($message, $placeholders);
    if ($this->io === NULL) {
      Drupal::messenger()->addStatus($message);
    } else {
      $this->io->success($message);
    }
  }

  public function section(string $message, array $placeholders = []) {
    $message = $this->prepare($message, $placeholders);
    if ($this->io !== NULL) {
      $this->io->section($message);
    }
  }

}
