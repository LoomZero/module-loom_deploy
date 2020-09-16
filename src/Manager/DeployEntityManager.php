<?php

namespace Drupal\loom_deploy\Manager;

use Drupal;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use function array_flip;

class DeployEntityManager {

  /** @var DeployManager */
  private $manager = NULL;

  /** @var EntityTypeManagerInterface */
  private $etm = NULL;

  public function __construct(DeployManager $manager) {
    $this->manager = $manager;
  }

  public function entityTypeManager(): EntityTypeManagerInterface {
    if ($this->etm === NULL) {
      $this->etm = Drupal::entityTypeManager();
    }
    return $this->etm;
  }

  public function create(string $ident, string $type, array $fields = [], array $keys = NULL): EntityInterface {
    $entity = $this->prepare($ident, $type, $fields, $keys);
    if ($entity->isNew()) {
      return $this->save($ident, $entity);
    } else {
      return $entity;
    }
  }

  public function prepare(string $ident, string $type, array $fields = [], array $keys = NULL): EntityInterface {
    $state = $this->manager->checkDeployIdent($ident, TRUE);
    if ($state === NULL) {
      $storage = $this->entityTypeManager()->getStorage($type);

      if ($keys !== NULL) {
        $keys = array_flip($keys);
        foreach ($keys as $key => $index) {
          $keys[$key] = $fields[$key];
        }
        $entities = $storage->loadByProperties($keys);
        $entity = reset($entities);
        if ($entity) {
          $this->manager->log('The entity [type] already exist so bind it to ident [ident]', [
            'type' => $type,
            'ident' => $ident,
          ]);

          $this->manager->setDeployIdent($ident, 'entity', [
            'id' => $entity->id(),
            'type' => $entity->getEntityTypeId(),
          ]);
          return $entity;
        }
      }

      $this->manager->log('Prepare entity [type] with identifier [ident]', [
        'type' => $type,
        'ident' => $ident,
      ]);
      return $this->entityTypeManager()->getStorage($type)->create($fields);
    } else {
      return $this->entityTypeManager()->getStorage($state['value']['type'])->load($state['value']['id']);
    }
  }

  public function save(string $ident, EntityInterface $entity): EntityInterface {
    $this->manager->log('Save entity with identifier [ident].', [
      'ident' => $ident,
    ]);
    if ($entity instanceof FieldableEntityInterface) {
      $error = [];
      foreach ($entity->validate() as $violation) {
        $error[] = 'Field ' . $violation->getPropertyPath() . ': ' . $violation->getMessage();
      }
      if (count($error)) {
        $this->manager->setError('Error on create ' . $entity->bundle() . ' ' . $ident, $error);
      }
    }
    $entity->save();
    $this->manager->setDeployIdent($ident, 'entity', [
      'id' => $entity->id(),
      'type' => $entity->getEntityTypeId(),
    ]);
    return $entity;
  }

}
