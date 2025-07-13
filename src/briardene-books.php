<?php
/*
 * Plugin Name: Briardene Books
 * Description: Customizations for the Briardene Books WooCommerce store.
 * Version: {{ VERSION }}
 * Author: Andrew January
 * Author URI: https://ajanuary.com
 * License: MIT
 * Text Domain: briardene-books
 */

add_action('wp_head', 'briardene_add_custom_css');
function briardene_add_custom_css() {
  echo '<style type="text/css">';

  # Add 1px padding around thumbnails
  echo '
  .flex-control-nav .flex-control-thumbs {
    margin: 0 -1px;
  }

  .woocommerce div.product div.images .flex-control-thumbs li {
    padding: 2px 1px;
    box-sizing: border-box;
  }
  ';

  # Make zoom button translucent so you can read any text behind it
  echo '
  .woocommerce-product-gallery__trigger {
    opacity: 0.5;
    transition: opacity 0.1s ease-in-out;
  }

  .woocommerce-product-gallery__trigger:hover {
    opacity: 1;
  }
  ';

  # Hide the variation price, and adjust the padding.
  # Variation price is not needed as the price is shown in the dropdown.
  echo '
  .woocommerce-variation-price {
    display: none;
  }

  .woocommerce div.product form.cart .variations {
    margin-bottom: 0;
  }

  .woocommerce div.product form.cart .woocommerce-variation-description p {
    margin-top: 0;
  }
  ';


  # Styling the author
  echo  '
  .wp-block-post-title .briardene-title {
    font-weight: bold;
  }

  .wp-block-post-title .briardene-sep {
    display: none;
  }

  .wp-block-post-title .briardene-author {
    display: block;
    font-weight: normal;
    line-height: 1;
    color: #666A66;
  }
  ';

  # Price list block styles
  echo '
  .briardene-price-item {
    font-weight: bold;
  }

  .briardene-price-description {
    margin: 0 0 1em 0;
  }

  .briardene-price-description p {
    margin: 0;
  }
  ';

  echo '</style>';
}


// Split product titles on " - " and wrap in spans
add_filter('the_title', 'briardene_modify_product_title', 10, 2);
function briardene_modify_product_title($title, $post_id = null) {
  if (is_admin() || empty($title) || !$post_id || get_post_type($post_id) !== 'product') {
    return $title;
  }

  $product = wc_get_product($post_id);
  if ($product) {
    $author = $product->get_attribute('Author');
    if (!empty($author)) {
      return '<span class="briardene-title">' . esc_html($title) . '</span><span class="briardene-sep" aria-label="by"> — </span><span class="briardene-author">' . esc_html($author) . '</span>';
    }
  }
  return '<span class="briardene-title">' . esc_html($title) . '</span>';
}


// Translate "Select options" to "Buy now"
add_filter('gettext', 'briardene_translate_woocommerce_strings', 999, 3);
function briardene_translate_woocommerce_strings($translated, $untranslated, $domain) {
  if (!is_admin() && 'woocommerce' === $domain) {
    switch ($translated) {
      case 'Select options':
        $translated = 'Buy now';
        break;
    }
  }   

  return $translated;
}


// Add price to the variation dropdown
add_filter('woocommerce_dropdown_variation_attribute_options_html', 'briardene_show_price_in_attribute_dropdown', 10, 2);
function briardene_show_price_in_attribute_dropdown($html, $args) {
  if (sizeof($args['product']->get_variation_attributes()) != 1) {
    return $html;
  }

  $options = $args['options'];
  $product = $args['product'];
  $attribute = $args['attribute'];
  $name = $args['name'] ? $args['name'] : 'attribute_' . sanitize_title($attribute);
  $id = $args['id'] ? $args['id'] : sanitize_title($attribute);
  $class = $args['class'];
  $show_option_none = $args['show_option_none'] ? true : false;
  $show_option_none_text = $args['show_option_none'] ? $args['show_option_none'] : __('Choose an option', 'woocommerce');

  if (empty($options) && !empty($product) && !empty($attribute)) {
    $attributes = $product->get_variation_attributes();
    $options = $attributes[$attribute];
  }

  $html = '<select id="' . esc_attr($id) . '" class="' . esc_attr($class) . '" name="' . esc_attr($name) . '" data-attribute_name="attribute_' . esc_attr(sanitize_title($attribute)) . '" data-show_option_none="' . ($show_option_none ? 'yes' : 'no') . '">';
  $html .= '<option value="">' . esc_html($show_option_none_text) . '</option>';

  if (!empty($options)) {
    if ($product && taxonomy_exists($attribute)) {
      $terms = wc_get_product_terms($product->get_id(), $attribute, array('fields' => 'all'));
      foreach ($terms as $term) {
        if (in_array($term->slug, $options)) {
          // Get and inserting the price
          $price_html = get_the_variation_price_html($product, $name, $term->slug);
          $html .= '<option value="' . esc_attr($term->slug) . '" ' . selected(sanitize_title($args['selected']), $term->slug, false) . '>' . esc_html(apply_filters('woocommerce_variation_option_name', $term->name) . ' (' . $price_html) . ')</option>';
        }
      }
    } else {
      foreach ($options as $option) {
        $selected = sanitize_title($args['selected']) === $args['selected'] ? selected($args['selected'], sanitize_title($option), false) : selected($args['selected'], $option, false);
        // Get and inserting the price
        $price_html = get_the_variation_price_html($product, $name, $option);
        $html .= '<option value="' . esc_attr($option) . '" ' . $selected . '>' . esc_html(apply_filters('woocommerce_variation_option_name', $option) . ' (' . $price_html . ')') . '</option>';
      }
    }
  }
  $html .= '</select>';

  return $html;
}

function get_the_variation_price_html($product, $name, $term_slug) {
  foreach ($product->get_available_variations() as $variation) {
    if ($variation['attributes'][$name] == $term_slug) {
      return strip_tags($variation['price_html']);
    }
  }
}


// Hide price block for variable products, as the price is shown in the dropdown
add_filter('woocommerce_get_price_html', 'briardene_hide_variable_price', 10, 2);
function briardene_hide_variable_price($price, $product) {
  // Only hide on single product pages for variable products
  if (is_product() && $product->is_type('variable')) {
    return '';
  }
  return $price;
}


// Register Price List block and enqueue editor script with dependencies
add_action('init', function() {
    // Register the block script with dependencies
    wp_register_script(
        'briardene-price-list-editor',
        plugins_url('blocks/price-list/index.js', __FILE__),
        array('wp-blocks', 'wp-i18n', 'wp-element', 'wp-server-side-render'),
        filemtime(__DIR__ . '/blocks/price-list/index.js')
    );

    // Register the block type and associate the script
    register_block_type(__DIR__ . '/blocks/price-list', array(
        'editor_script' => 'briardene-price-list-editor',
        'render_callback' => 'briardene_render_price_list_block',
        'attributes' => array(
            'productId' => array(
                'type' => 'integer',
                'default' => 0,
            ),
        ),
    ));
});

function briardene_render_price_list_block($attributes, $content) {
  global $product;

  // Show static preview if still no product
  if ((defined('REST_REQUEST') && REST_REQUEST) && !$product) {
    return '<p><em>[Price list will be shown here]</em></p>';
  }

  if (!$product) {
    return '';
  }

  if ($product->is_type('simple')) {
    $price_html = $product->get_price_html();
    return '<p class="briardene-price-item">' . wp_kses_post($price_html) . '</p>';
  }

  if (!$product->is_type('variable')) {
    return '';
  }

  $variations = $product->get_available_variations();
  $attributes = $product->get_variation_attributes();
  $attribute_keys = array_keys($attributes);

  if (empty($variations) || empty($attribute_keys)) {
    $price_html = $product->get_price_html();
    return '<p class="briardene-price-item">' . wp_kses_post($price_html) . '</p>';
  }

  $html = '<dl class="briardene-price-list">';
  foreach ($variations as $variation) {
    $name_parts = [];
    foreach ($attribute_keys as $attr) {
      $value = isset($variation['attributes']['attribute_' . strtolower($attr)]) ? $variation['attributes']['attribute_' . strtolower($attr)] : '';
      if ($value) {
        if (taxonomy_exists($attr)) {
          $display_value = $value;
          $terms = wc_get_product_terms($product->get_id(), $attr, array('fields' => 'all'));

          foreach ($terms as $term) {
            if ($term->slug === $value) {
              $display_value = $term->name;
              break;
            }
          }
          $filter_attr = $attr;
        } else {
          $display_value = $value;
          $filter_attr = $attr;
        }
        $label = apply_filters('woocommerce_variation_option_name', $display_value, $filter_attr, $product);
        $name_parts[] = esc_html($label);
      }
    }
    $variation_name = implode(', ', $name_parts);
    $price_html = isset($variation['price_html']) ? $variation['price_html'] : '';
    $description = isset($variation['variation_description']) ? $variation['variation_description'] : '';
    $html .= '<dt class="briardene-price-item">';
    $html .= esc_html($variation_name) . ' (';
    $html .= wp_kses_post($price_html) . ')</dt>';
    if ($description) {
      $html .= ' <dd class="briardene-price-description">' . $description . '</dd>';
    }
  }
  $html .= '</dl>';
  return $html;
}
?>
