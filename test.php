<?php 
/**
* Plugin Name: Check Update from github test
* Plugin URI: https://www.yourwebsiteurl.com/
* Description: This is the very first plugin I ever created.
* Version: 6.6
* Author: Musadiq Mehmood
* Author URI: http://yourwebsiteurl.com/
**/

ob_start();
require 'plugin-update-checker/plugin-update-checker.php';
$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
    'https://github.com/musadiq012/ldprogithubupdatetesting/',
    __FILE__,
    'ldprogithubupdatetesting'
);

//Set the branch that contains the stable release.
$myUpdateChecker->setBranch('master');

//Optional: If you're using a private repository, specify the access token like this:
//$myUpdateChecker->setAuthentication('your-token-here');

ob_clean();

function add_my_stylesheet1() {
    wp_enqueue_style( 'myStyles', plugins_url( __FILE__ ) . '/styles.css' );
}
add_action( 'admin_enqueue_scripts', 'add_my_stylesheet1' );

///////////////
add_shortcode( 'showcourseshortcode', 'scsc_shortcode' );
function scsc_shortcode() {
global $post; $post_id = $post->ID;
$user_id   = get_current_user_id();
$args = array(
    'post_type' => 'sfwd-courses',
    'orderby' => 'post_date',
    'order' => 'DESC',
    'posts_per_page' => -1,
    'post_status' => 'publish',
    );
$q = new WP_Query($args);
?>
      
       <?php
    if ($q->have_posts()) : while ($q->have_posts()) : $q->the_post(); 


$post_type = get_post_type( $q->ID );
$course_id = $q->ID;
$cg_short_description = get_post_meta( $q->ID, '_learndash_course_grid_short_description', true );
$enable_video = get_post_meta( $q->ID, '_learndash_course_grid_enable_video_preview', true );
$embed_code   = get_post_meta( $q->ID, '_learndash_course_grid_video_embed_code', true );
$button_text  = get_post_meta( $q->ID, '_learndash_course_grid_custom_button_text', true );
//$course_title = the_title();
$button_link = learndash_get_step_permalink( get_the_ID(), $course_id );

$is_completed = false;
if ( $post_type == 'sfwd-courses' ) {
    $has_access   = sfwd_lms_has_access( $course_id, $user_id );
    $is_completed = learndash_course_completed( $user_id, $course_id );
} elseif ( $post_type == 'groups' ) {
    $has_access = learndash_is_user_in_group( $user_id, $q->ID );
    $is_completed = learndash_get_user_group_completed_timestamp( $q->ID, $user_id );
} elseif ( $post_type == 'sfwd-lessons' ) {
    $parent_course_id = $shortcode_atts['course_id'] ?? learndash_get_course_id( $q->ID );
    $has_access   = sfwd_lms_has_access( $parent_course_id, $user_id );
    $is_completed = learndash_is_lesson_complete( $user_id, $q->ID, $parent_course_id );

} elseif ( $post_type == 'sfwd-topic' ) {
    $parent_course_id = $shortcode_atts['course_id'] ?? learndash_get_course_id( $q->ID );
    $has_access   = sfwd_lms_has_access( $parent_course_id, $user_id );
    $is_completed = learndash_is_topic_complete( $user_id, $q->ID, $parent_course_id );
}




// Course Options

$course_options = get_post_meta( $q->ID, "_sfwd-courses", true );
// For LD >= 3.0
$price = '';
$price_type = '';
if ( function_exists( 'learndash_get_course_price' ) && function_exists( 'learndash_get_group_price' ) ) {
    if ( $post_type == 'sfwd-courses' ) {
        $price_args = learndash_get_course_price( $course_id );
    } elseif ( $post_type == 'groups' ) {
        $price_args = learndash_get_group_price( $q->ID );
    }

    if ( ! empty( $price_args ) ) {
        $price      = $price_args['price'];
        $price_type = $price_args['type'];
    }
} else {
    $price = $course_options && isset($course_options['sfwd-courses_course_price']) ? $course_options['sfwd-courses_course_price'] : __( 'Free', 'learndash-course-grid' );
    $price_type = $course_options && isset( $course_options['sfwd-courses_course_price_type'] ) ? $course_options['sfwd-courses_course_price_type'] : '';
}


//-=-=-=- Price With Currency -=-==--=-==--=-==--=-==--=-==--=-==--=-==--=-==--=-==--=-==-

$currency_setting = class_exists( 'LearnDash_Settings_Section' ) ? LearnDash_Settings_Section::get_section_setting( 'LearnDash_Settings_Section_PayPal', 'paypal_currency' ) : null;
$currency = '';

if ( isset( $currency_setting ) || ! empty( $currency_setting ) ) {
    $currency = $currency_setting;
} elseif ( isset( $options['modules'] ) && isset( $options['modules']['sfwd-courses_options'] ) && isset( $options['modules']['sfwd-courses_options']['sfwd-courses_paypal_currency'] ) ) {
    $currency = $options['modules']['sfwd-courses_options']['sfwd-courses_paypal_currency'];
}

if ( class_exists( 'NumberFormatter' ) ) {
    $locale = get_locale();
    $number_format = new NumberFormatter( $locale . '@currency=' . $currency, NumberFormatter::CURRENCY );
    $currency = $number_format->getSymbol( NumberFormatter::CURRENCY_SYMBOL );
}

/**
 * Currency symbol filter hook
 * 
 * @param string $currency Currency symbol
 * @param int    $course_id
 */
$currency = apply_filters( 'learndash_course_grid_currency', $currency, $course_id );

$price_text = '';

if ( is_numeric( $price ) && ! empty( $price ) ) {
    $price_format = apply_filters( 'learndash_course_grid_price_text_format', '{currency}{price}' );

    $price_text = str_replace(array( '{currency}', '{price}' ), array( $currency, $price ), $price_format );
} elseif ( is_string( $price ) && ! empty( $price ) ) {
    $price_text = $price;
} elseif ( empty( $price ) ) {
    $price_text = __( 'Free', 'learndash-course-grid' );
}
//-=-==--=-==--=-==--=-==--=-==--=-==--=-==--=-==--=-==--=-==--=-==--=-==--=-==-

// Ribbon Text taking course title
$ribbon_text = get_post_meta( $q->ID, '_learndash_course_grid_custom_ribbon_text', true );
$ribbon_text = isset( $ribbon_text ) && ! empty( $ribbon_text ) ? $ribbon_text : '';
$ribbon = '';

if ( in_array( $post_type, [ 'sfwd-courses', 'groups' ] ) ) {
    if ( $has_access && $is_completed && $price_type != 'open' && empty( $ribbon_text ) ) {
        $ribbon = 'Completed';
    } elseif ( $price_type == 'open' && empty( $ribbon_text ) ) {
        if ( is_user_logged_in() && ! $is_completed ) {
            $ribbon = 'Enrolled';
        } elseif ( is_user_logged_in() && $is_completed ) {
            $ribbon = 'Completed';
        } else {
            $ribbon = '';
        }
    } elseif ( $price_type == 'closed' && empty( $price ) ) {

        if ( $is_completed ) {
        } else {
        }

        if ( is_numeric( $price ) ) {
            $ribbon = $price_text;
        } else {
            $ribbon = '';
        }
    } else {
        if ( empty( $ribbon_text ) ) {
            $ribbon = $price_text;
        } else {
        }
    }
} elseif ( in_array( $post_type, ['sfwd-lessons', 'sfwd-topic'] ) ) {
    if ( $has_access && $is_completed ) {
        $ribbon = 'Completed';
    } elseif ( $has_access && ! $is_completed ) {
        $ribbon =  'In progress';
    } else {
        $ribbon = 'Not available';
    }
}

////////////////////////////////////////////////////////////////////////
?>

 <!-- New -->
 <div>
    <div class="column">
      <div class="card">
          <!-- Card Image and Ribbion -->
        <div class="card-image">
        <?php echo get_the_post_thumbnail( $q->ID, 'large' );
             if(!empty($ribbon)){
                    echo '<div class="ribbon ribbon-top-left"><span>'.$ribbon.'</span></div>';
                } ?>
        </div>
   
       
        <!-- Card Title -->
        <h3 class="card-title">
        <?php echo the_title( '<a href="' . esc_url( get_permalink() ) . '"  rel="course title">', '</a>') ?>
        </h3>
        <!-- Card Detail -->
        <!-- <p class="course_desc"> -->
        <?php echo the_excerpt();?>
        <!-- </p> -->
       
<button class="button_button1">Python</button>
        <!-- Card PRogress bar -->
        <!-- <div id="progress"></div> -->
      </div>
    </div>
</div>

<?php
////////////////////////////////////////////////////////////////////////////


//echo $ribbon_text .' / <br>';
//echo '<br> Ribbon Text : '.$ribbon_text .' / <br>';
//echo ' Price : '.$price .' / <br>';
//echo 'Price Type : '.$price_type .' / <br>';


 endwhile;  else:
     _e('No Posts Sorry.');
 endif;
 ?> 
     
   
<?php
}
?>

    