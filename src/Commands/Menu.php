<?php

namespace Drupal\dst_entity_generate\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\dst_entity_generate\BaseEntityGenerate;
use Drupal\dst_entity_generate\DstegConstants;
use Drupal\dst_entity_generate\Services\GeneralApi;

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
   * DstegMenu constructor.
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
   * Generate all the Drupal Menus from Drupal Spec tool sheet.
   *
   * @command dst:generate:menus
   * @aliases dst:m
   * @usage drush dst:generate:menus
   */
  public function generateMenus() {
    $this->io()->success('Generating Drupal Menus.');
    $entity_data = $this->getDataFromSheet(DstegConstants::MENUS);
    if (!empty($entity_data)) {
      $menus_data = $this->getMenuData($entity_data);
      $menu_storage = $this->entityTypeManager->getStorage('menu');
      $menus = $menu_storage->loadMultiple();
      foreach ($menus_data as $menu) {
        $menu_name = $menu['label'];
        if ($menus[$menu['id']]) {
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
      $menu = [];
      $description = isset($item['description']) ? $item['description'] : $item['name'] . ' menu.';
      $menu['id'] = $item['machine_name'];
      $menu['label'] = $item['title'];
      $menu['description'] = $description;

      \array_push($menu_types, $menu);
    }
    return $menu_types;

  }

}
