<?php

namespace Drupal\dst_entity_generate\Commands;

use Drupal\dst_entity_generate\BaseEntityGenerate;
use Drupal\dst_entity_generate\DstegConstants;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\dst_entity_generate\Services\GeneralApi;

/**
 * Class provides functionality of Custom Block Type generation from DST sheet.
 *
 * @package Drupal\dst_entity_generate\Commands
 */
class CustomBlockType extends BaseEntityGenerate {
  /**
   * {@inheritDoc}
   */
  protected $entity = 'custom_block_type';

  /**
   * {@inheritDoc}
   */
  protected $dstEntityName = 'block_types';

  /**
   * Array of all dependent modules.
   *
   * @var array
   */
  protected $dependentModules = ['block'];

  /**
   * Entity Type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * List of required fields to create entity.
   *
   * @var array
   */
  protected $requiredFields = ['name', 'machine_name'];

  /**
   * Construct the Custom Block Type class object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity Type manager.
   * @param \Drupal\dst_entity_generate\Services\GeneralApi $generalApi
   *   The helper service for DSTEG.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, GeneralApi $generalApi) {
    $this->entityTypeManager = $entityTypeManager;
    $this->helper = $generalApi;
  }

  /**
   * Generate all the Drupal custom block type from DEG sheet.
   *
   * @command deg:generate:custom_block_type
   * @aliases deg:cbt
   * @usage drush deg:cbt
   *   Generates custom block types with fields if not present.
   * @usage drush deg:cbt --update
   *   Update existing entity types with fields and creates new if not present.
   * @option update Update existing entity types with fields and creates new if not present.
   */
  public function generateCustomBlockType($options = ['update' => FALSE]) {
    $this->io()->success('Generating Drupal Custom Block Type...');
    $this->updateMode = $options['update'];
    $mode = 'create';
    if ($this->updateMode) {
      $mode = 'update';
    }
    $data = $this->getDataFromSheet(DstegConstants::BUNDLES);
    $block_content_storage = $this->entityTypeManager->getStorage('block_content_type');
    $block_content_types = $this->getCustomBlockTypeData($data);

    foreach ($block_content_types as $index => $block_content_type) {
      $id = $block_content_type['id'];
      $block_type = $block_content_storage->load($id);
      if (!\is_null($block_type)) {
        if ($this->updateMode && $data[$index][$this->implementationFlagColumn] === $this->updateFlag) {
          $this->updateEntityType($block_type, $block_content_type);
          $this->io()->success("Block Type $id updated.");
          continue;
        }
        $this->io()->warning("Custom Block Type $id Already exists. Skipping creation...");
        continue;
      }
      $status = $block_content_storage->create($block_content_type)->save();
      if ($status === SAVED_NEW) {
        $this->io()->success("Custom Block Type $id is successfully created...");
      }
    }

    // Generate fields now.
    $bundle_type = 'Block type';
    $fields_data = $bundles_data = [];
    $fields_data = $this->getDataFromSheet(DstegConstants::FIELDS, FALSE);
    $fields_data = $this->filterEntityTypeSpecificData($fields_data, 'bundle');

    if (empty($fields_data)) {
      $this->io()->warning("There is no data from the sheet. Skipping Generating fields data for $bundle_type.");
      return self::EXIT_SUCCESS;
    }
    foreach ($block_content_types as $block_content_type) {
      $bundles_data[$block_content_type['label']] = $block_content_type['id'];
    }

    $this->helper->generateEntityFields($bundle_type, $fields_data, $bundles_data, $mode);
  }

  /**
   * Get data needed for custom block type entity.
   *
   * @param array $data
   *   Array of Data.
   *
   * @return array|null
   *   Custom block type compliant data.
   */
  private function getCustomBlockTypeData(array $data) {
    $block_content_types = [];
    foreach ($data as $item) {
      if (!$this->requiredFieldsCheck($item, 'Custom block type')) {
        continue;
      }
      if (!$this->validateMachineName($item['machine_name'])) {
        continue;
      }
      $block_content = [];
      $block_content['label'] = $item['name'];
      $block_content['id'] = $item['machine_name'];
      $block_content['description'] = $item['description'];
      \array_push($block_content_types, $block_content);
    }
    return $block_content_types;
  }

}
