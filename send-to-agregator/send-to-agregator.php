<?php

/**
 * Plugin Name: Send to Agregator
 * Description: Allows to send articles directly to Agregator App.
 * Version: 0.1
 * Author: Piotr Ryczek
 */

$api_url = 'https://articles-agregator.nero12.usermd.net';

function activate_sta()
{
}

function deactivate_sta()
{
}

register_activation_hook(__FILE__, 'activate_sta');
register_deactivation_hook(__FILE__, 'deactivate_sta');
add_action('admin_menu', 'sta_add_options_page');
add_action('add_meta_boxes', 'sta_add_box');
add_action('wp_ajax_sta_send', 'sta_send');
add_action('wp_ajax_nopriv_sta_send', 'sta_send');

function sta_add_options_page()
{
  add_options_page('Send to Agregator', 'Send to Agregator', 'manage_options', 'send-to-agregator', 'sta_options_page');
}

function sta_options_page()
{
  if (!empty($_POST)) {
    update_option('sta_api_key', $_POST['api_key']);
    update_option('sta_api_password', $_POST['api_password']);
  }

  $api_key = get_option('sta_api_key');
  $api_password = get_option('sta_api_password');

?>
  <div class="wrap">
    <h1>Ustawienia API Agregatora</h1>
    <form method="POST">
      <table class="form-table">
        <tbody>
          <tr>
            <th>Api Key</th>
            <td>
              <input name="api_key" type="text" value="<?= $api_key; ?>" class="regular-text code">
            </td>
          </tr>
          <tr>
            <th>Api Password</th>
            <td>
              <input name="api_password" type="text" value="<?= $api_password; ?>" class="regular-text code">
            </td>
          </tr>
        </tbody>
      </table>

      <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Zapisz zmiany"></p>
    </form>
  </div>
<?php
}

function sta_add_box()
{
  add_meta_box(
    'sta_box',
    'Wyślij do Agregatora',
    'sta_box',
    ['post']
  );
}

function sta_box()
{
  global $api_url;
  $response = wp_remote_get($api_url . '/regions');
  $regions = json_decode($response['body'])->regions;

?>
  <div id="sta_form">
    <p>Pamiętaj, aby przed wysłaniem najpierw <strong>zapisać</strong> wpis. Jeśli wysłałeś już wcześniej wpis o danym ID to kolejne akcje będą skutkować jego aktualizacją.</p>
    <p id="sta_message"></p>
    <input type="hidden" name="sta_post_id" value="<?= get_the_ID(); ?>" />
    <table class="form-table">
      <tbody>
        <tr>
          <th><select name="sta_region_id" style="width: 100%;">
              <option value="0">Wybierz region</option>
              <?php foreach ($regions as $region) : ?>
                <option value="<?= $region->_id; ?>">
                  <?= $region->title; ?>
                </option>
              <?php endforeach; ?>
            </select></th>
          <td>
            <button type="submit" class="button button-primary" id="sta_submit">Wyślij</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
  <script type="text/javascript">
    jQuery(document).ready(function() {
      jQuery('#sta_submit').on('click', (event) => {
        event.preventDefault();

        const postId = jQuery('[name="sta_post_id"]').val();
        const regionId = jQuery('[name="sta_region_id"]').val();

        jQuery('#sta_submit').prop('disabled', true);

        jQuery.post(ajaxurl, {
          action: 'sta_send',
          post_id: postId,
          region_id: regionId
        }, function(response) {
          jQuery('#sta_submit').prop('disabled', false);

          if (response === 'ok') {
            jQuery('#sta_message').text('Operacja wykonana poprawnie.');
          } else {
            jQuery('#sta_message').text(response);
          }
        });
      })
    })
  </script>
<?php
}

function sta_send()
{
  global $api_url;

  $post_id = $_POST['post_id'];
  $region_id = $_POST['region_id'];

  if (empty($post_id)) {
    echo 'Brakuje ID wpisu.';
    exit;
  }

  if ($region_id == 0) {
    echo 'Brakuje ID regionu.';
    exit;
  }

  $post = get_post($post_id);
  $thumbnail_url = get_the_post_thumbnail_url($post_id, 'full');

  $body = array(
    'wordpressId' => $post_id,
    'title' => $post->post_title,
    'excerpt' => $post->post_excerpt,
    'content' => $post->post_content,
    'regionId' => $region_id,
  );

  if ($thumbnail_url) {
    $body['photoUrl'] = $thumbnail_url;
  }

  $response = wp_remote_post($api_url . '/publishersApi/articles', array(
    'headers' => array(
      'apikey' => get_option('sta_api_key'),
      'authorization' => 'Basic ' . get_option('sta_api_password')
    ),
    'body' => $body
  ));

  $body = json_decode($response['body']);

  if (!empty($body->errorCode)) {
    echo $body->errorCode;
    exit;
  }

  echo 'ok';
  exit;
}
