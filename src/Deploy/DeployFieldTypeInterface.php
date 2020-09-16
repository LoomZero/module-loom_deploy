<?php

namespace Drupal\loom_deploy\Deploy;

use Drupal\Core\Form\FormStateInterface;

interface DeployFieldTypeInterface {

  public function types(): array;

  public function widget(array $field): array;

  public function validate(array $element, array $field, $value, FormStateInterface $form_state);

  public function toValue(array $field, $value);

}
