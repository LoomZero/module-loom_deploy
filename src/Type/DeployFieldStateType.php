<?php

namespace Drupal\loom_deploy\Type;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\ctools\SerializableTempstore;
use Drupal\loom_deploy\Deploy\DeployFieldTypeInterface;
use function array_pop;
use function serialize;
use function unserialize;

class DeployFieldStateType implements DeployFieldTypeInterface {

  public function types(): array {
    return ['state'];
  }

  public function widget(array $field, FormStateInterface $form_state): array {
    $id = 'widget-container-' . $field['ident'];

    return [
      '#type' => 'container',
      '#attributes' => [
        'id' => $id,
      ],
      'value' => [
        '#type' => 'textarea',
        '#default_value' => serialize($field['value']['value']),
        '#description' => 'The value for the state <pre class="deploy-field-pre">' . $field['value']['state'] . '</pre>',
      ],
      'format' => [
        '#type' => 'select',
        '#title' => 'Format',
        '#options' => [
          'serialize' => 'Serialize',
          'yml' => 'YML',
        ],
        '#default_value' => 'serialize',
        '#ajax' => [
          'callback' => [$this, 'changeFormat'],
          'event' => 'change',
          'wrapper' => $id,
        ],
      ],
    ];
  }

  public function validate(array $element, array $field, $value, FormStateInterface $form_state) {
    $a = 0;
  }

  public function toValue(array $field, $value) {
    return $value;
  }

  public function load(array $field, $value) {

  }

  public function changeFormat(&$form, FormStateInterface $form_state, $form_id) {
    $trigger = $form_state->getTriggeringElement();
    $parents = $trigger['#parents'];
    array_pop($parents);
    $container = NestedArray::getValue($form, $parents);

    $value = $container['value']['#value'];

    switch ($container['format']['#value']) {
      case 'yml':
        $value = unserialize($value);
        $value = Yaml::encode($value);
        break;
      default:
        $value = Yaml::decode($value);
        $value = serialize($value);
        break;
    }

    $container['value']['#value'] = $value;
    return $container;
  }

}
