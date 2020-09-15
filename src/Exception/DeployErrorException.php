<?php

namespace Drupal\loom_deploy\Exception;

use Throwable;

class DeployErrorException extends DeployException {

  private $title = NULL;
  private $more = NULL;
  private $error = NULL;

  /**
   * @param string $title
   * @param string|?Throwable $more
   * @param Throwable|null $error
   */
  public function __construct(string $title, $more = NULL, Throwable $error = NULL) {
    if ($more instanceof Throwable) {
      $error = $more;
      $more = NULL;
    }

    parent::__construct($title, 0, $error);
    $this->title = $title;
    $this->more = $more;
    $this->error = $error;
  }

  public function getTitle(): string {
    return $this->title;
  }

  public function getMore(): ?string {
    return $this->more;
  }

  public function getError(): ?Throwable {
    return $this->error;
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

}
