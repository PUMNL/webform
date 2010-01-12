<?php
// $Id$

/**
 * @file
 * Sample hooks demonstrating usage in Webform.
 */

/**
 * @defgroup webform_component Sample Webform Component
 * @{
 * Webform's hooks enable other modules to intercept events within Webform, such
 * as the completion of a submission or adding validation. Webform's hooks also
 * allow other modules to provide additional components for use within forms.
 */

/**
 * Define components to Webform.
 *
 * @return
 *   An array of components, keyed by machine name. Required properties are
 *   "label" and "description". An optional "file" may be specified to be loaded
 *   when the component is needed. A set of callbacks will be established based
 *   on the name of the component. All components follow the pattern:
 *
 *   _webform_[callback]_[component]
 *
 *   Where [component] is the name of the key of the component and [callback] is
 *   any of the following:
 *
 *     - defaults
 *     - theme
 *     - edit
 *     - form_builder_types
 *     - form_builder_save
 *     - form_builder_preview_alter
 *     - delete
 *     - render
 *     - display
 *     - analysis
 *     - table
 *     - csv_headers
 *     - csv_data
 *
 * See the sample component implementation for details on each one of these
 * callbacks.
 *
 * @see webform_component
 */
function hook_webform_component_info() {
  $components = array();

  $components['textfield'] = array(
    'label' => t('Textfield'),
    'description' => t('Basic textfield type.'),
    'file' => 'components/textfield.inc',
  );

  return $components;
}

/**
 * Alter the list of available Webform components.
 *
 * @param $components
 *   A list of existing components as defined by hook_webform_component_info().
 *
 * @see hook_webform_component_info()
 */
function hook_webform_component_info_alter(&$components) {
  // Completely remove a component.
  unset($components['grid']);

  // Change the name of a component.
  $components['textarea']['label'] = t('Text box');
}

/**
 * @}
 */

/**
 * @defgroup webform_component Sample Webform Component
 * @{
 * In each of these examples, the word "component" should be replaced with the,
 * name of the component type (such as textfield or select). These are not
 * actual hooks, but instead samples of how Webform integrates with its own
 * built-in components.
 */

/**
 * Specify the default properties of a component.
 *
 * @return
 *   An array defining the default structure of a component.
 */
function _webform_defaults_component() {
  return array(
    'name' => '',
    'form_key' => NULL,
    'email' => 1,
    'mandatory' => 0,
    'pid' => 0,
    'weight' => 0,
    'extra' => array(
      'options' => '',
      'questions' => '',
      'optrand' => 0,
      'qrand' => 0,
      'description' => '',
    ),
  );
}

/**
 * Generate the form for editing a component.
 *
 * Create a set of form elements to be displayed on the form for editing this
 * component. Use care naming the form items, as this correlates directly to the
 * database schema. The component "Name" and "Description" fields are added to
 * every component type and are not necessary to specify here (although they
 * may be overridden if desired).
 *
 * @param $component
 *   A Webform component array.
 * @return
 *   An array of form items to be displayed on the edit component page
 */
function _webform_edit_component($component) {
  $form = array();

  // Disabling the description if not wanted.
  $form['description'] = array();

  // Most options are stored in the "extra" array, which stores any settings
  // unique to a particular component type.
  $form['extra']['options'] = array(
    '#type' => 'textarea',
    '#title' => t('Options'),
    '#default_value' => $component['extra']['options'],
    '#description' => t('Key-value pairs may be entered separated by pipes. i.e. safe_key|Some readable option') . theme('webform_token_help'),
    '#cols' => 60,
    '#rows' => 5,
    '#weight' => -3,
    '#required' => TRUE,
  );
  return $form;
}

/**
 * Define the component to structure to hook_form_builder_types().
 */
function _webform_form_builder_types_email() {
  $fields = array();

  $fields['email'] = array(
    'title' => t('E-mail'),
    'properties' => array(
      'title',
      'description',
      'default_value',
      'required',
      'size',
      'key',
    ),
    'default' => array(
      '#title' => t('New e-mail'),
      '#type' => 'textfield',
      '#form_builder' => array('element_type' => 'email'),
    ),
  );

  return $fields;
}

/**
 * Convert a FAPI form element into settings savable in a component.
 *
 * @param $component
 *   The Webform component to be saved.
 * @param $form_element
 *   The form element as edited by the user through Form Builder.
 */
function _webform_form_builder_save_component(&$component, $form_element) {
  $component['extra']['width'] = isset($form_element['#size']) ? $form_element['#size'] : NULL;
  $component['extra']['description'] = isset($form_element['#description']) ? $form_element['#description'] : NULL;
  $component['extra']['disabled'] = isset($form_element['#disabled']) ? $form_element['#disabled'] : FALSE;
}

/**
 * Module specific instance of hook_form_builder_preview_alter().
 */
function _webform_form_builder_preview_alter_hidden(&$form_element) {
  // Make hidden fields visible while editing.
  $form_element['#field_suffix'] = '(' . t('hidden') . ')';
  $form_element['#autocomplete_path'] = NULL; // Needed to avoid notices.
  $form_element['#type'] = 'textfield';
}

/**
 * Render a Webform component to be part of a form.
 *
 * @param $component
 *   A Webform component array.
 * @param $value
 *   If editing an existing submission or resuming a draft, this will contain
 *   an array of values to be shown instead of the default in the component
 *   configuration. This value will always be an array, keyed numerically for
 *   each value saved in this field.
 */
function _webform_render_component($component, $value = NULL) {
  $form_item = array(
    '#type' => 'textfield',
    '#title' => $component['name'],
    '#required' => $component['mandatory'],
    '#weight' => $component['weight'],
    '#description'   => _webform_filter_descriptions($component['extra']['description']),
    '#default_value' => $component['value'],
    '#prefix' => '<div class="webform-component-'. $component['type'] .'" id="webform-component-'. $component['form_key'] .'">',
    '#suffix' => '</div>',
  );

  if (isset($value)) {
    $form_item['#default_value'] = $value[0];
  }

  return $form_item;
}

/**
 * Display the result of a submission for a component.
 * 
 * The output of this function will be displayed under the "Results" tab then
 * "Submissions". This should output the saved data in some reasonable manner.
 *
 * @param $component
 *   A Webform component array.
 * @param $value
 *   An array of information containing the submission result, directly
 *   correlating to the webform_submitted_data database table schema.
 * @return
 *   Textual output formatted for human reading.
 */
function _webform_display_component($component, $value, $enabled = FALSE) {
  $form_item = _webform_render_component($component, FALSE);
  $cid = 0;
  foreach (element_children($form_item) as $key) {
    $form_item[$key]['#default_value'] = $value[$cid++];
    $form_item[$key]['#disabled'] = !$enabled;
  }
  return $form_item;
}

/**
 * A hook for changing the input values before saving to the database.
 *
 * Note that Webform will save the result of this function directly into the
 * database.
 *
 * @param $component
 *   A Webform component array.
 * @param $value
 *   The POST data associated with the user input.
 * @return
 *   An array of values to be saved into the database. Note that this should be
 *   a numerically keyed array.
 */
function _webform_submit_component($component, $value) {
  // Clean up a phone number into 123-456-7890 format.
  if ($component['extra']['phone_number']) {
    $matches = array();
    $number = preg_replace('[^0-9]', $value[0]);
    if (strlen($number) == 7) {
      $number = substr($number, 0, 3) . '-' . substr($number, 3, 4);
    }
    else {
      $number = substr($number, 0, 3) . '-' . substr($number, 3, 3) . '-' . substr($number, 6, 4);
    }
  }

  $value[0] = $number;
  return $value;
}

/**
 * Delete operation for a component or submission.
 *
 * @param $component
 *   A Webform component array.
 * @param $data
 *   An array of information containing the submission result, directly
 *   correlating to the webform_submitted_data database schema.
 */
function _webform_delete_component($component, $value) {
  // Delete corresponding files when a submission is deleted.
  $filedata = unserialize($value['0']);
  if (isset($filedata['filepath']) && is_file($filedata['filepath'])) {
    unlink($filedata['filepath']);
    db_query("DELETE FROM {files} WHERE filepath = '%s'", $filedata['filepath']);
  }
}

/**
 * Module specific instance of hook_help().
 *
 * This allows each Webform component to add information into hook_help().
 */
function _webform_help_component($section) {
  switch ($section) {
    case 'admin/settings/webform#grid_description':
      return t('Allows creation of grid questions, denoted by radio buttons.');
  }
}

/**
 * Module specific instance of hook_theme().
 *
 * This allows each Webform component to add information into hook_theme().
 */
function _webform_theme_component() {
  return array(
    'webform_grid' => array(
      'arguments' => array('grid_element' => NULL),
    ),
    'webform_mail_grid' => array(
      'arguments' => array('component' => NULL, 'value' => NULL),
    ),
  );
}

/**
 * Calculate and returns statistics about results for this component.
 * 
 * This takes into account all submissions to this webform. The output of this
 * function will be displayed under the "Results" tab then "Analysis".
 *
 * @param $component
 *   An array of information describing the component, directly correlating to
 *   the webform_component database schema.
 * @param $sids
 *   An optional array of submission IDs (sid). If supplied, the analysis will
 *   be limited to these sids.
 * @return
 *   An array of data rows, each containing a statistic for this component's
 *   submissions.
 */
function _webform_analysis_component($component, $sids = array()) {
  // Generate the list of options and questions.
  $options = _webform_component_options($component['extra']['options']);
  $questions = array_values(_webform_component_options($component['extra']['questions']));

  // Generate a lookup table of results.
  $placeholders = count($sids) ? array_fill(0, count($sids), "'%s'") : array();
  $sidfilter = count($sids) ? " AND sid in (".implode(",", $placeholders).")" : "";
  $query = 'SELECT no, data, count(data) as datacount '.
    ' FROM {webform_submitted_data} '.
    ' WHERE nid = %d '.
    ' AND cid = %d '.
    " AND data != '' ". $sidfilter .
    ' GROUP BY no, data';
  $result = db_query($query, array_merge(array($component['nid'], $component['cid']), $sids));
  $counts = array();
  while ($data = db_fetch_object($result)) {
    $counts[$data->no][$data->data] = $data->datacount;
  }

  // Create an entire table to be put into the returned row.
  $rows = array();
  $header = array('');

  // Add options as a header row.
  foreach ($options as $option) {
    $header[] = $option;
  }

  // Add questions as each row.
  foreach ($questions as $qkey => $question) {
    $row = array($question);
    foreach ($options as $okey => $option) {
      $row[] = !empty($counts[$qkey][$okey]) ? $counts[$qkey][$okey] : 0;
    }
    $rows[] = $row;
  }
  $output = theme('table', $header, $rows, array('class' => 'webform-grid'));

  return array(array(array('data' => $output, 'colspan' => 2)));
}

/**
 * Return the result of a component value for display in a table.
 *
 * The output of this function will be displayed under the "Results" tab then
 * "Table".
 *
 * @param $component
 *   A Webform component array.
 * @param $value
 *   An array of information containing the submission result, directly
 *   correlating to the webform_submitted_data database schema.
 * @return
 *   Textual output formatted for human reading.
 */
function _webform_table_component($component, $value) {
  $questions = array_values(_webform_component_options($component['extra']['questions']));
  $output = '';
  // Set the value as a single string.
  if (is_array($value)) {
    foreach ($value as $item => $value) {
      if ($value !== '') {
        $output .= $questions[$item] .': '. check_plain($value) .'<br />';
      }
    }
  }
  else {
    $output = check_plain(!isset($value['0']) ? '' : $value['0']);
  }
  return $output;
}

/**
 * Return the header for this component to be displayed in a CSV file.
 *
 * The output of this function will be displayed under the "Results" tab then
 * "Download".
 *
 * @param $component
 *   A Webform component array.
 * @return
 *   An array of data to be displayed in the first three rows of a CSV file, not
 *   including either prefixed or trailing commas.
 */
function _webform_csv_headers_component($component) {
  $header = array();
  $header[0] = array('');
  $header[1] = array($component['name']);
  $items = _webform_component_options($component['extra']['questions']);
  $count = 0;
  foreach ($items as $key => $item) {
    // Empty column per sub-field in main header.
    if ($count != 0) {
      $header[0][] = '';
      $header[1][] = '';
    }
    // The value for this option.
    $header[2][] = $item;
    $count++;
  }

  return $header;
}

/**
 * Format the submitted data of a component for CSV downloading.
 *
 * The output of this function will be displayed under the "Results" tab then
 * "Download".
 *
 * @param $component
 *   A Webform component array.
 * @param $value
 *   An array of information containing the submission result, directly
 *   correlating to the webform_submitted_data database schema.
 * @return
 *   An array of items to be added to the CSV file. Each value within the array
 *   will be another column within the file. This function is called once for
 *   every row of data.
 */
function _webform_csv_data_component($component, $value) {
  $questions = array_keys(_webform_select_options($component['extra']['questions']));
  $return = array();
  foreach ($questions as $key => $question) {
    $return[] = isset($value[$key]) ? $value[$key] : '';
  }
  return $return;
}

/**
 * @}
 */
