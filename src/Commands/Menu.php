<?php

namespace Drupal\dst_entity_generate\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\dst_entity_generate\BaseEntityGenerate;
use Drupal\dst_entity_generate\DstegConstants;

/**
 * Class provides functionality of Menus generation from DST sheet.
 *
 * @package Drupal\dst_entity_generate\Commands
 */
class Menu extends BaseEntityGenerate {
  /**
   * {@inheritDoc}
   */
  protected $dstEntityName = 'menus';

  /**
   * Array of all dependent modules.
   *
   * @var array
   */
  protected $dependentModules = ['menu_ui'];

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
  protected $requiredFields = ['title', 'machine_name'];

  /**
   * DstegMenu constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity Type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Generate all the Drupal menus from DEG sheet.
   *
   * @command deg:generate:menus
   * @aliases deg:m
   * @usage drush deg:m
   *   Generates menus if not present.
   * @usage drush deg:m --update
   *   Generates Menus if not present also updates existing.
   * @option update Update existing entity types with fields and creates new if not present.
   */
  public function generateMenus($options = ['update' => FALSE]) {
    $this->io()->success('Generating Drupal Menus.');
    $this->updateMode = $options['update'];
    $data = $this->getDataFromSheet(DstegConstants::MENUS);
    if (!empty($data)) {
      $menus_data = $this->getMenuData($data);
      $menu_storage = $this->entityTypeManager->getStorage('menu');
      $menus = $menu_storage->loadMultiple();
      foreach ($menus_data as $index => $menu) {
        $menu_name = $menu['label'];
        if ($menus[$menu['id']]) {
          if ($this->updateMode && $data[$index][$this->implementationFlagColumn] === $this->updateFlag) {
            $this->updateEntityType($menus[$menu['id']], $menu);
            $this->io()->success("Menu $menu_name updated.");
            continue;
          }
          $this->io()->warning("Menu $menu_name Already exists. Skipping creation...");
          continue;
        }
        $status = $menu_storage->create($menu)->save();
        if ($status === SAVED_NEW) {
          $this->io()->success("Menu $menu_name is successfully created...");
        }
      }
    }
    else {
      $this->io()->warning('There is no data for the Menu entity in your DST sheet.');
    }
  }

  /**
   * Get data needed for Menu entity.
   *
   * @param array $data
   *   Array of Menus.
   *
   * @return array|null
   *   Menus compliant data.
   */
  private function getMenuData(array $data) {
    $menu_types = [];
    foreach ($data as $item) {
      if (!$this->requiredFieldsCheck($item, 'Menu')) {
        continue;
      }
      if (!$this->validateMachineName($item['machine_name'], 32, '-_')) {
        continue;
      }
      $menu = [];
      $description = $item['description'] ?? $item['name'] . ' menu.';
      $menu['id'] = str_replace('_', '-', $item['machine_name']);
      $menu['label'] = $item['title'];
      $menu['description'] = $description;

      \array_push($menu_types, $menu);
    }
    return $menu_types;
  }

}
