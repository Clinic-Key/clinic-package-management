<?php
/*
 * Plugin Name:       Clinic packages manager - clinic portal
 * Description:       A custom plugin made for allowing clinic owners to add packages
 * Version:           1.0.4
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Kazmi Webwhiz
 * Author URI:        https://kazmiwebwhiz.com/
 */

// Add Shortcode
function package_post_form_shortcode() {
    $current_user = wp_get_current_user();
    $args = array(
        'post_type' => 'clinic',
        'author' => $current_user->ID,
        'post_status' => array('publish', 'draft', 'pending'),
        'posts_per_page' => 1,
    );
    $existing_clinics = get_posts($args);
    $selected_clinic = !empty($existing_clinics) ? $existing_clinics[0] : null;

    // Fetch the available package categories
    $package_categories = get_terms(array(
        'taxonomy' => 'package-category',
        'hide_empty' => false,
    ));

    ob_start();
    ?>
    <form id="package-post-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
        <input type="hidden" name="action" value="handle_package_post_form">
        
        <div class="form-group">
            <label for="package_title">Title:</label>
            <input type="text" id="package_title" name="package_title" required>
        </div>

        <div class="form-group">
            <label for="package_description">Description:</label>
            <?php wp_editor('', 'package_description', array('textarea_name' => 'package_description')); ?>
        </div>

        <div class="form-group">
            <label for="price">Price:</label>
            <input type="number" id="price" name="price" required>
        </div>

        <div class="form-group">
            <label for="package_image">Image:</label>
            <input type="file" id="package_image" name="package_image" accept="image/*" required>
        </div>

        <div class="form-group">
            <label for="clinic">Clinic:</label>
            <select id="clinic" name="clinic" required>
                <?php if ($selected_clinic) : ?>
                    <option value="<?php echo esc_attr($selected_clinic->ID); ?>"><?php echo esc_html($selected_clinic->post_title); ?></option>
                <?php else : ?>
                    <option value="">No clinic found</option>
                <?php endif; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="package_category">Category:</label>
            <select id="package_category" name="package_category" required>
                <option value="">Select a category</option>
                <?php
                if (!empty($package_categories) && !is_wp_error($package_categories)) {
                    foreach ($package_categories as $category) {
                        echo '<option value="' . esc_attr($category->term_id) . '">' . esc_html($category->name) . '</option>';
                    }
                }
                ?>
            </select>
        </div>

        <div class="form-group">
            <label for="additional_benefits">Additional Benefits:</label>
            <div id="benefits-wrapper">
                <div class="benefit">
                    <input type="text" name="benefit[]" placeholder="Benefit">
                    <button type="button" class="remove-benefit">Remove</button>
                </div>
            </div>
            <button type="button" id="add-benefit">Add Benefit</button>
        </div>

        <div class="form-group">
            <label for="information_related_to_treatment">Information Related to Treatment:</label>
            <div id="information-wrapper">
                <div class="information">
                    <input type="text" name="question[]" placeholder="Question">
                    <textarea name="answer[]" placeholder="Answer"></textarea>
                    <button type="button" class="remove-information">Remove</button>
                </div>
            </div>
            <button type="button" id="add-information">Add Information</button>
        </div>

        <input type="submit" name="submit_package" value="Submit">
    </form>

    <style>
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .form-group input[type="text"],
        .form-group input[type="file"],
        .form-group input[type="number"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            box-sizing: border-box;
        }
        .benefit, .information {
            margin-bottom: 20px;
            padding: 10px;
            border: 1px solid #ddd;
        }
        .benefit input,
        .information input,
        .information textarea,
        .benefit button,
        .information button {
            width: calc(100% - 22px);
            margin-bottom: 10px;
        }
        .benefit button,
        .information button {
            width: auto;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('submitted') && urlParams.get('submitted') === 'true') {
                alert('Package submitted successfully!');
            }

            document.getElementById('add-benefit').addEventListener('click', function() {
                var benefitWrapper = document.createElement('div');
                benefitWrapper.classList.add('benefit');
                benefitWrapper.innerHTML = '<input type="text" name="benefit[]" placeholder="Benefit"><button type="button" class="remove-benefit">Remove</button>';
                document.getElementById('benefits-wrapper').appendChild(benefitWrapper);
            });

            document.addEventListener('click', function(event) {
                if (event.target.classList.contains('remove-benefit')) {
                    event.target.parentElement.remove();
                }
            });

            document.getElementById('add-information').addEventListener('click', function() {
                var informationWrapper = document.createElement('div');
                informationWrapper.classList.add('information');
                informationWrapper.innerHTML = '<input type="text" name="question[]" placeholder="Question"><textarea name="answer[]" placeholder="Answer"></textarea><button type="button" class="remove-information">Remove</button>';
                document.getElementById('information-wrapper').appendChild(informationWrapper);
            });

            document.addEventListener('click', function(event) {
                if (event.target.classList.contains('remove-information')) {
                    event.target.parentElement.remove();
                }
            });
        });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('package_post_form', 'package_post_form_shortcode');

function create_woocommerce_product_for_package($package_id) {
    $package_title = get_the_title($package_id);
    $package_price = get_post_meta($package_id, 'price', true);
    $package_image_id = get_post_thumbnail_id($package_id);

    // Create WooCommerce Product
    $product = new WC_Product_Simple();
    $product->set_name($package_title);
    $product->set_regular_price($package_price);
    $product->set_status('pending'); // Set product status to pending
    $product->set_catalog_visibility('visible');

    // Set Product Image
    if ($package_image_id) {
        $product->set_image_id($package_image_id);
    }

    // Save Product
    $product_id = $product->save();

    // Get Product URL
    $product_url = get_permalink($product_id);

    // Store Product URL in Package
    update_post_meta($package_id, 'woocommerce_product_url', $product_url);
}

function handle_package_post_form_submission() {
    if (isset($_POST['submit_package'])) {
        $package_title = sanitize_text_field($_POST['package_title']);
        $package_description = wp_kses_post($_POST['package_description']);
        $price = floatval($_POST['price']);
        $clinic_id = intval($_POST['clinic']);
        $package_category = intval($_POST['package_category']);

        // Upload Package Image
        if (!empty($_FILES['package_image']['name'])) {
            if ($_FILES['package_image']['error'] == UPLOAD_ERR_OK) {
                require_once(ABSPATH . 'wp-admin/includes/file.php'); // Ensure this file is included
                $upload_overrides = array('test_form' => false);
                $uploaded_image = wp_handle_upload($_FILES['package_image'], $upload_overrides);
                if ($uploaded_image && !isset($uploaded_image['error'])) {
                    $image_url = $uploaded_image['url'];
                } else {
                    wp_die('There was an error uploading the image.');
                }
            } else {
                wp_die('Error during file upload.');
            }
        }

        // Create Custom Post
        $new_post = array(
            'post_title'    => $package_title,
            'post_status'   => 'pending', // Set package status to pending
            'post_type'     => 'package', // Replace 'package' with your actual custom post type
        );

        $post_id = wp_insert_post($new_post);

        // Set Package Image
        if (!empty($image_url) && !is_wp_error($post_id)) {
            $attachment = array(
                'post_mime_type' => 'image/jpeg',
                'post_title'     => $package_title,
                'post_content'   => '',
                'post_status'    => 'inherit',
            );
            $attach_id = wp_insert_attachment($attachment, $image_url, $post_id);
            require_once(ABSPATH . 'wp-admin/includes/image.php'); // Ensure this file is included
            $attach_data = wp_generate_attachment_metadata($attach_id, $image_url);
            wp_update_attachment_metadata($attach_id, $attach_data);
            set_post_thumbnail($post_id, $attach_id);
        }

        // Save Additional Fields
        if (!is_wp_error($post_id)) {
            update_post_meta($post_id, 'package_description', $package_description);
            update_post_meta($post_id, 'price', $price);
            update_field('clinic', $clinic_id, $post_id);

            // Set package category
            if ($package_category) {
                wp_set_post_terms($post_id, array($package_category), 'package-category');
            }

            if (isset($_POST['benefit'])) {
                $benefits = array();
                foreach ($_POST['benefit'] as $benefit) {
                    $benefits[] = array('benefit' => sanitize_text_field($benefit));
                }
                update_field('additional_benefits', $benefits, $post_id);
            }

            if (isset($_POST['question'])) {
                $information = array();
                foreach ($_POST['question'] as $key => $question) {
                    $information[] = array(
                        'question' => sanitize_text_field($question),
                        'answer'   => sanitize_textarea_field($_POST['answer'][$key])
                    );
                }
                update_field('information_related_to_treatment', $information, $post_id);
            }

            // Create WooCommerce Product
            create_woocommerce_product_for_package($post_id);
        }

        // Redirect to the same page with a success query parameter
        $redirect_url = add_query_arg('submitted', 'true', $_SERVER['HTTP_REFERER']);
        wp_redirect($redirect_url);
        exit;
    }
}
add_action('admin_post_handle_package_post_form', 'handle_package_post_form_submission');
add_action('admin_post_nopriv_handle_package_post_form', 'handle_package_post_form_submission');

function display_user_packages_shortcode() {
    $current_user = wp_get_current_user();
    $args = array(
        'post_type' => 'package',
        'author' => $current_user->ID,
        'post_status' => array('publish', 'pending'),
        'posts_per_page' => -1,
    );
    $user_packages = get_posts($args);

    if (empty($user_packages)) {
        return '<p>You have not created any packages yet.</p>';
    }

    $output = '<div class="user-packages">';
    foreach ($user_packages as $package) {
        $package_id = $package->ID;
        $package_title = get_the_title($package_id);
        $package_status = $package->post_status;
        $package_image_url = get_the_post_thumbnail_url($package_id, 'medium');
        $package_view_url = get_permalink($package_id);

        $output .= '<div class="package-card">';
        if ($package_image_url) {
            $output .= '<img src="' . esc_url($package_image_url) . '" alt="' . esc_attr($package_title) . '" class="package-image">';
        }
        $output .= '<div class="package-details">';
        $output .= '<h3>' . esc_html($package_title) . '</h3>';
        if ($package_status === 'publish') {
            $output .= '<p class="package-status published">Status: Published</p>';
            $output .= '<a href="' . esc_url($package_view_url) . '" class="view-package-button" target="_blank">View Package</a>';
        } else {
            $output .= '<p class="package-status pending">Status: Pending</p>';
        }
        $output .= '</div>';
        $output .= '</div>';
    }
    $output .= '</div>';

    $output .= '<style>
        .user-packages {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            padding: 20px;
        }
        .package-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: calc(33.333% - 20px);
            margin-bottom: 20px;
        }
        .package-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .package-details {
            padding: 15px;
        }
        .package-details h3 {
            margin-top: 0;
            font-size: 1.2em;
        }
        .package-status {
            margin: 10px 0;
            padding: 5px 10px;
            border-radius: 4px;
        }
        .package-status.published {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .package-status.pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        .view-package-button {
            display: inline-block;
            background: #06C0D8;
            color: white;
            margin-top: 10px;
            padding: 10px 15px;
            color: #fff;
            border-radius: 4px;
            text-decoration: none;
        }
        .view-package-button:hover {
            background: #011685;
            color: #fff !important;
        }
    </style>';

    return $output;
}
add_shortcode('display_user_packages', 'display_user_packages_shortcode');


function display_clinic_name_shortcode() {
    global $post;

    if (get_post_type($post) == 'package') {
        $clinic_id = get_field('clinic', $post->ID);
        if ($clinic_id && is_array($clinic_id)) {
            $clinic_id = $clinic_id[0]; // Ensure we're getting the actual ID from the array
        }
        if ($clinic_id) {
            $clinic_name = get_the_title($clinic_id);
            return '<p>' . esc_html($clinic_name) . '</p>';
        }
    }

    return '';
}
add_shortcode('display_clinic_name', 'display_clinic_name_shortcode');

function display_additional_benefits_shortcode() {
    global $post;

    if (get_post_type($post) == 'package') {
        $additional_benefits = get_field('additional_benefits', $post->ID);
        if ($additional_benefits) {
            $output = '<div class="additional-benefits">';
            foreach ($additional_benefits as $benefit) {
                $output .= '<div class="benefit-item">';
                $output .= '<span class="benefit-icon">&#10003;</span>'; // Checkmark icon
                $output .= '<span class="benefit-text">' . esc_html($benefit['benefit']) . '</span>';
                $output .= '</div>';
            }
            $output .= '</div>';

            $output .= '<style>
                .additional-benefits {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 10px;
                    padding: 20px;
                    background: #f9f9f9;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                }
                .benefit-item {
                    display: flex;
                    align-items: center;
                    background: #fff;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    padding: 10px;
                    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
                }
                .benefit-icon {
                    margin-right: 10px;
                    color: green;
                }
                .benefit-text {
                    font-weight: bold;
                }
            </style>';

            return $output;
        }
    }

    return '';
}
add_shortcode('display_additional_benefits', 'display_additional_benefits_shortcode');

function display_information_related_to_treatment_shortcode() {
    global $post;

    if (get_post_type($post) == 'package') {
        $information = get_field('information_related_to_treatment', $post->ID);
        if ($information) {
            $output = '<div class="information-related-to-treatment">';
            foreach ($information as $info) {
                $output .= '<div class="info-item">';
                $output .= '<div class="info-question">' . esc_html($info['question']) . '</div>';
                $output .= '<div class="info-answer" style="display: none;">' . esc_html($info['answer']) . '</div>';
                $output .= '</div>';
            }
            $output .= '</div>';

            $output .= '<style>
                .information-related-to-treatment {
                    margin-top: 20px;
                    padding: 20px;
                    background: #f9f9f9;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                }
                .info-item {
                    margin-bottom: 15px;
                    padding: 15px;
                    background: #fff;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
                    cursor: pointer;
                }
                .info-question {
                    font-weight: bold;
                    font-size: 1.2em;
                }
                .info-answer {
                    margin-top: 10px;
                    margin-left: 20px;
                }
            </style>';

            $output .= '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    const infoItems = document.querySelectorAll(".info-item");
                    infoItems.forEach(item => {
                        item.addEventListener("click", function() {
                            const answer = this.querySelector(".info-answer");
                            if (answer.style.display === "none" || answer.style.display === "") {
                                answer.style.display = "block";
                            } else {
                                answer.style.display = "none";
                            }
                        });
                    });
                });
            </script>';

            return $output;
        }
    }

    return '';
}
add_shortcode('display_information_related_to_treatment', 'display_information_related_to_treatment_shortcode');
?>
