<?php

namespace Drupal\loom_deploy\Deploy;

use Drupal\Core\Form\FormStateInterface;

interface DeployFieldTypeInterface {

  public function types(): array;

  public function widget(array $field, FormStateInterface $form_state): array;

  public function validate(array $element, array $field, $value, FormStateInterface $form_state);

  public function toValue(array $field, $value);

  public function load(array $field, $value);

}
