<?php

namespace Drupal\webform\Plugin\Field\FieldWidget;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Utility\WebformDateHelper;
use Drupal\webform\WebformInterface;

/**
 * Plugin implementation of the 'webform_entity_reference_autocomplete' widget.
 *
 * @FieldWidget(
 *   id = "webform_entity_reference_autocomplete",
 *   label = @Translation("Autocomplete"),
 *   description = @Translation("An autocomplete text field."),
 *   field_types = {
 *     "webform"
 *   }
 * )
 */
class WebformEntityReferenceAutocompleteWidget extends EntityReferenceAutocompleteWidget {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'default_data' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    $element['default_data'] = [
      '#type' => 'checkbox',
      '#title' => t('Enable default submission data (YAML)'),
      '#description' => t('If checked, site builders will be able to define default submission data (YAML)'),
      '#default_value' => $this->getSetting('default_data'),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $summary[] = t('Default submission data: @default_data', ['@default_data' => $this->getSetting('default_data') ? $this->t('Yes') : $this->t('No')]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    if (!isset($items[$delta]->status)) {
      $items[$delta]->status = WebformInterface::STATUS_OPEN;
    }

    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    // Get field name.
    $field_name = $items->getName();

    // Get field input name from field parents, field name, and the delta.
    $field_parents = array_merge($element['target_id']['#field_parents'], [$field_name, $delta]);
    $field_input_name = (array_shift($field_parents)) . ('[' . implode('][', $field_parents) . ']');

    // Set element 'target_id' default properties.
    $element['target_id'] += [
      '#weight' => 0,
    ];

    // Get weight.
    $weight = $element['target_id']['#weight'];

    $element['settings'] = [
      '#type' => 'details',
      '#title' => $this->t('@title settings', ['@title' => $element['target_id']['#title']]),
      '#element_validate' => [[$this, 'validateOpenClose']],
      '#open' => ($items[$delta]->target_id) ? TRUE : FALSE,
      '#weight' => $weight++,
    ];

    $element['settings']['status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Status'),
      '#options' => [
        WebformInterface::STATUS_OPEN => $this->t('Open'),
        WebformInterface::STATUS_CLOSED => $this->t('Closed'),
        WebformInterface::STATUS_SCHEDULED => $this->t('Scheduled'),
      ],
      '#options_display' => 'side_by_side',
      '#default_value' => $items[$delta]->status,
    ];

    $element['settings']['scheduled'] = [
      '#type' => 'item',
      '#title' => $element['target_id']['#title'],
      '#title_display' => 'invisible',
      '#input' => FALSE,
      '#states' => [
        'visible' => [
          'input[name="' . $field_input_name . '[settings][status]"]' => ['value' => WebformInterface::STATUS_SCHEDULED],
        ],
      ],
    ];
    $element['settings']['scheduled']['open'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Open'),
      '#default_value' => $items[$delta]->open ? DrupalDateTime::createFromTimestamp(strtotime($items[$delta]->open)) : NULL,
      '#prefix' => '<div class="container-inline form-item">',
      '#suffix' => '</div>',
      '#help' => FALSE,
      '#description' => [
        '#type' => 'webform_help',
        '#help' => $this->t('If the open date/time is left blank, this form will immediately be opened.'),
        '#help_title' => $this->t('Open'),
      ],
    ];
    $element['settings']['scheduled']['close'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Close'),
      '#default_value' => $items[$delta]->close ? DrupalDateTime::createFromTimestamp(strtotime($items[$delta]->close)) : NULL,
      '#prefix' => '<div class="container-inline form-item">',
      '#suffix' => '</div>',
      '#help' => FALSE,
      '#description' => [
        '#type' => 'webform_help',
        '#help' => $this->t('If the close date/time is left blank, this webform will never be closed.'),
        '#help_title' => $this->t('Close'),
      ],
    ];

    if ($this->getSetting('default_data')) {
      /** @var \Drupal\webform\WebformTokenManagerInterface $token_manager */
      $token_manager = \Drupal::service('webform.token_manager');

      $element['settings']['default_data'] = [
        '#type' => 'webform_codemirror',
        '#mode' => 'yaml',
        '#title' => $this->t('Default submission data (YAML)'),
        '#placeholder' => $this->t("Enter 'name': 'value' pairs…"),
        '#default_value' => $items[$delta]->default_data,
        '#webform_element' => TRUE,
        '#description' => [
          'content' => ['#markup' => $this->t('Enter submission data as name and value pairs as <a href=":href">YAML</a> which will be used to prepopulate the selected webform.', [':href' => 'https://en.wikipedia.org/wiki/YAML']), '#suffix' => ' '],
          'token' => $token_manager->buildTreeLink(),
        ],
        '#more_title' => $this->t('Example'),
        '#more' => [
          '#theme' => 'webform_codemirror',
          '#type' => 'yaml',
          '#code' => " # This is an example of a comment.
element_key: 'some value'

# The below example uses a token to get the current node's title.
# Add ':clear' to the end token to return an empty value when the token is missing.
title: '[webform_submission:node:title:clear]'
# The below example uses a token to get a field value from the current node.
full_name: '[webform_submission:node:field_full_name:clear]"
        ],
      ];
      $element['settings']['token_tree_link'] = $token_manager->buildTreeElement();
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    parent::massageFormValues($values, $form, $form_state);

    // Massage open/close dates.
    // @see \Drupal\webform\WebformEntitySettingsForm::save
    // @see \Drupal\datetime\Plugin\Field\FieldWidget\DateTimeWidgetBase::massageFormValues
    foreach ($values as &$item) {
      $item += $item['settings'];
      unset($item['settings']);

      if ($item['status'] === WebformInterface::STATUS_SCHEDULED) {
        $states = ['open', 'close'];
        foreach ($states as $state) {
          if (!empty($item['scheduled'][$state]) && $item['scheduled'][$state] instanceof DrupalDateTime) {
            $item[$state] = WebformDateHelper::formatStorage($item['scheduled'][$state]);
          }
          else {
            $item[$state] = '';
          }
        }
      }
      else {
        $item['open'] = '';
        $item['close'] = '';
      }
      unset($item['scheduled']);
    }
    return $values;
  }

  /**
   * Validate callback to ensure that the open date <= the close date.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   generic form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @see \Drupal\webform\WebformEntitySettingsForm::validateForm
   * @see \Drupal\datetime_range\Plugin\Field\FieldWidget\DateRangeWidgetBase::validateOpenClose
   */
  public function validateOpenClose(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $status = $element['status']['#value'];
    if ($status === WebformInterface::STATUS_SCHEDULED) {
      $open_date = $element['scheduled']['open']['#value']['object'];
      $close_date = $element['scheduled']['close']['#value']['object'];

      // Require open or close dates.
      if (empty($open_date) && empty($close_date)) {
        $form_state->setError($element['scheduled']['open'], $this->t('Please enter an open or close date'));
        $form_state->setError($element['scheduled']['close'], $this->t('Please enter an open or close date'));
      }

      // Make sure open date is not after close date.
      if ($open_date instanceof DrupalDateTime && $close_date instanceof DrupalDateTime) {
        if ($open_date->getTimestamp() !== $close_date->getTimestamp()) {
          $interval = $open_date->diff($close_date);
          if ($interval->invert === 1) {
            $form_state->setError($element['scheduled']['open'], $this->t('The @title close date cannot be before the open date', ['@title' => $element['#title']]));
          }
        }
      }
    }
  }

}
