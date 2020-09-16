<?php

namespace Drupal\loom_deploy\Form;

use Drupal;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\loom_deploy\Manager\DeployFormManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\loom_deploy\Manager\DeployManager;

class DeployStateForm extends FormBase {

  /** @var DeployManager */
  protected $loomDeployManager;
  /** @var DeployFormManager */
  protected $loomFormManager;

  private $cache = NULL;
  private $formWrapper = 'deploy-state-form-fields';

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('loom.deploy.manager'),
      $container->get('loom.deploy.form.manager')
    );
  }

  public function __construct(DeployManager $loomDeployManager, DeployFormManager $loomFormManager) {
    $this->loomDeployManager = $loomDeployManager;
    $this->loomFormManager = $loomFormManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'deploy_state_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $fields = $this->getDeployFields();

    $form['fields'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $this->formWrapper,
      ],
    ];
    if (count($fields)) {
      foreach ($fields as $key => $field) {
        $form['fields'][$key] = $this->getField($field);
      }
    } else {
      $form['fields']['info'] = [
        '#markup' => 'There are no fields! Use <pre class="deploy-field-pre">drupal loom:deploy</pre> to execute deploy functions.',
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = 'loom_deploy/deploy_field';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $fields = $this->getDeployFields();
    $values = $form_state->getValue('fields');

    foreach ($fields as $key => $field) {
      $type = $this->loomFormManager->getTypeClass($field['type']);
      if ($type !== NULL) {
        $type->validate($form['fields'][$key], $field, $values[$key]['widget']['value'], $form_state);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $fields = $this->getDeployFields();
    $values = $form_state->getValue('fields');

    foreach ($fields as $key => $field) {
      $type = $this->loomFormManager->getTypeClass($field['type']);
      if ($type === NULL) {
        $field['value'] = unserialize($values[$key]['widget']['value']);
        $field['edit'] = TRUE;
      } else {
        $field['value'] = $type->toValue($field, $values[$key]['widget']['value']);
        $field['edit'] = TRUE;
      }
      Drupal::state()->set($key, $field);
    }
  }

  public function getField(array $field): array {
    return [
      '#type' => 'fieldset',
      '#title' => 'Ident: ' . $field['ident'],
      '#attributes' => [
        'class' => [
          'deploy-field-wrapper',
          $field['ident'],
        ],
      ],
      'widget' => [
        '#type' => 'container',
        'value' => $this->loomFormManager->getWidget($field),
      ],
      'description' => [
        '#theme' => 'deploy_field_description',
        '#field' => $field,
      ],
      'remove' => [
        '#type' => 'button',
        '#value' => 'Sure?',
        '#name' => 'remove_' . $field['ident'],
        '#attributes' => [
          'class' => [
            'deploy-field-delete',
          ],
        ],
        '#ajax' => [
          'callback' => '::removeValue',
          'wrapper' => $this->formWrapper,
          'effect' => 'fade',
        ],
      ],
    ];
  }

  public function getDeployFields() {
    if ($this->cache === NULL) {
      $this->cache = [];
      $select = Database::getConnection()->select('key_value', 'k');
      $select->fields('k', ['name', 'value']);
      $select->condition('k.name', 'loom_deploy.%', 'LIKE');

      foreach ($select->execute()->fetchAllKeyed() as $key => $value) {
        $this->cache[$key] = unserialize($value);
      }
    }
    return $this->cache;
  }

  public function removeValue(&$form, FormStateInterface $form_state, $form_id) {
    $trigger = $form_state->getTriggeringElement();
    $field = $trigger['#parents'][1];
    Drupal::state()->delete($field);

    $form_state->setRebuild();
    unset($form['fields'][$field]);
    return $form['fields'];
  }

}
