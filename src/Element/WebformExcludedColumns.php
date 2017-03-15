<?php

namespace Drupal\webform\Element;

use Drupal\webform\Entity\Webform;

/**
 * Provides a webform element for webform excluded columns (submission field and elements).
 *
 * @FormElement("webform_excluded_columns")
 */
class WebformExcludedColumns extends WebformExcludedBase {

  /**
   * {@inheritdoc}
   */
  public static function getWebformExcludedHeader() {
    return [t('Title'), t('Name'), t('Date type/Element type')];
  }

  /**
   * {@inheritdoc}
   */
  public static function getWebformExcludedOptions(array $element) {
    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = Webform::load($element['#webform_id']);

    $options = [];

    /** @var \Drupal\webform\WebformSubmissionStorageInterface $submission_storage */
    $submission_storage = \Drupal::entityTypeManager()->getStorage('webform_submission');
    $field_definitions = $submission_storage->getFieldDefinitions();
    $field_definitions = $submission_storage->checkFieldDefinitionAccess($webform, $field_definitions);
    foreach ($field_definitions as $key => $field_definition) {
      $options[$key] = [
        ['title' => $field_definition['title']],
        ['name' => $key],
        ['type' => $field_definition['type']],
      ];
    }
    $options += parent::getWebformExcludedOptions($element);
    return $options;
  }

}
