<?php

namespace Drupal\cleanpager\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form to configure module settings.
 */
class CleanPagerSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    // Empty.
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cleanpager_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('cleanpager.settings');
    $settings = $config->get();
    $form['settings'] = [
      '#tree' => TRUE,
    ];
    $form['settings']['cleanpager_pages'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Pages'),
      '#description' => $this->t('Please set your pages where clean pagination should work. One path - one line.'),
      '#default_value' => $settings['cleanpager_pages'] ?? '',
    ];
    $form['settings']['cleanpager_add_trailing'] = [
      '#title' => $this->t('Add trailing slash'),
      '#description' => $this->t('Add a trailing slash (/) to all urls generated by Clean Pagination. I.E. "pager_url/page/1/"'),
      '#type' => 'checkbox',
      '#default_value' => $settings['cleanpager_add_trailing'] ?? FALSE,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('cleanpager.settings');
    $form_values = $form_state->getValues();
    $config
      ->set('cleanpager_pages', $form_values['settings']['cleanpager_pages'])
      ->set('cleanpager_add_trailing', $form_values['settings']['cleanpager_add_trailing'])
      ->save();
    parent::submitForm($form, $form_state);
  }

}
