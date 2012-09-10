<?php
/**
 * Name: Templates
 *
 * Description: An easy to use templating engine for Pods. Use {@field_name} magic tags to output values, within your HTML markup.
 *
 * Version: 2.0
 *
 * Menu Page: edit.php?post_type=_pods_template
 * Menu Add Page: post-new.php?post_type=_pods_template
 *
 * @package pods
 * @subpackage templates
 */

class Pods_Templates extends PodsComponent {

    /**
     * Pods object
     *
     * @var object
     *
     * @since 2.0.0
     */
    static $obj = null;

    /**
     * Do things like register/enqueue scripts and stylesheets
     *
     * @since 2.0.0
     */
    public function __construct () {
        $args = array(
            'label' => 'Pod Templates',
            'labels' => array( 'singular_name' => 'Pod Template' ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'query_var' => false,
            'rewrite' => false,
            'has_archive' => false,
            'hierarchical' => false,
            'supports' => array( 'title', 'author', 'revisions' ),
            'menu_icon' => PODS_URL . 'ui/images/icon16.png'
        );

        if ( !is_super_admin() )
            $args[ 'capability_type' ] = 'pods_template';

        $args = PodsInit::object_label_fix( $args, 'post_type' );

        register_post_type( '_pods_template', apply_filters( 'pods_internal_register_post_type_object_template', $args ) );

        add_action( 'dbx_post_advanced', array( $this, 'edit_page_form' ), 10 );
        add_action( 'pods_meta_save_post__pods_template', array( $this, 'clear_cache' ), 10, 5 );
        add_action( 'delete_post', array( $this, 'clear_cache' ), 10, 1 );
    }

    /**
     * Enqueue styles
     *
     * @since 2.0.0
     */
    public function admin_assets () {
        wp_enqueue_style( 'pods-admin' );
    }

    /**
     * Clear cache on save
     *
     * @since 2.0.0
     */
    public function clear_cache ( $data, $pod = null, $id = null, $groups = null, $post = null ) {
        if ( !is_array( $data ) && 0 < $data ) {
            $post = $data;
            $post = get_post( $post );

            if ( '_pods_template' != $post->post_type )
                return;
        }

        delete_transient( 'pods_object_template' );
        delete_transient( 'pods_object_template_' . $post->post_title );
    }

    /**
     * Change post title placeholder text
     *
     * @since 2.0.0
     */
    public function set_title_text ( $text, $post ) {
        return __( 'Enter template name here', 'pods' );
    }

    /**
     * Edit page form
     *
     * @since 2.0.0
     */
    public function edit_page_form () {
        global $post_type;

        if( '_pods_template' != $post_type )
        		return;

        add_filter( 'enter_title_here', array( $this, 'set_title_text' ), 10, 2 );

        $this->add_meta_boxes();
    }

    /**
     * Add meta boxes to the page
     *
     * @since 2.0.0
     */
    public function add_meta_boxes () {
        $pod = array(
            'name' => '_pods_template',
            'type' => 'post_type'
        );

        $fields = array(
            array(
                'name' => 'code',
                'label' => __( 'Content', 'pods' ),
                'type' => 'paragraph',
                'options' => array(
                    'paragraph_format_type' => 'codemirror'
                )
            )
        );

        pods_group_add( $pod, __( 'Template', 'pods' ), $fields, 'normal', 'high' );

        add_filter( 'update_post_metadata', array( $this, 'save_meta' ), 9, 5 );
        add_filter( 'get_post_metadata', array( $this, 'get_meta' ), 9, 4 );
    }

    /**
     * Save the fields
     *
     * @param $object_type
     * @param null $_null
     * @param int $object_id
     * @param string $meta_key
     * @param string $meta_value
     * @param string $prev_value
     *
     * @return bool|int|null
     */
    public function save_meta ( $_null = null, $object_id = 0, $meta_key = '', $meta_value = '', $prev_value = '' ) {
        if ( 'code' == $meta_key ) {
            $postdata = array(
                'ID' => $object_id,
                'post_content' => $meta_value
            );

            wp_update_post( $postdata );

            return true;
        }

        return $_null;
    }

    /**
     * Get the fields
     *
     * @param null $_null
     * @param int $object_id
     * @param string $meta_key
     * @param bool $single
     *
     * @return array|bool|int|mixed|null|string|void
     */
    public function get_meta ( $_null = null, $object_id = 0, $meta_key = '', $single = false ) {
        if ( 'code' == $meta_key ) {
            $post = get_post( $object_id );

            return $post->post_content;
        }

        return $_null;
    }

    /**
     * Display the page template
     *
     * @param string $template The template name
     * @param string $code Custom template code to use instead
     * @param object $obj The Pods object
     *
     * @since 2.0.0
     */
    public static function template ( $template, $code = null, $obj = null ) {
        if ( !empty( $obj ) )
            self::$obj =& $obj;
        else
            $obj =& self::$obj;

        if ( empty( $obj ) || !is_object( $obj ) )
            return '';

        if ( empty( $code ) && !empty( $template ) ) {
            $template = $obj->api->load_template( array( 'name' => $template ) );

            if ( !empty( $template ) && !empty( $template[ 'code' ] ) )
                $code = $template[ 'code' ];
        }

        $code = apply_filters( 'pods_templates_pre_template', $code, $template, $obj );
        $code = apply_filters( "pods_templates_pre_template_{$template}", $code, $template, $obj );

        ob_start();

        if ( !empty( $code ) ) {
            // Only detail templates need $this->id
            if ( empty( $obj->id ) ) {
                while ( $obj->fetch() ) {
                    echo self::do_template( $code );
                }
            }
            else
                echo self::do_template( $code );
        }

        $out = ob_get_clean();

        $out = apply_filters( 'pods_templates_post_template', $out, $code, $template, $obj );
        $out = apply_filters( "pods_templates_post_template_{$template}", $out, $code, $template, $obj );

        return $out;
    }

    /**
     * Parse a template string
     *
     * @param string $code The template string to parse
     * @param object $obj The Pods object
     *
     * @since 1.8.5
     */
    public static function do_template ( $code, $obj = null ) {
        if ( !empty( $obj ) )
            self::$obj =& $obj;
        else
            $obj =& self::$obj;

        if ( empty( $obj ) || !is_object( $obj ) )
            return '';

        ob_start();

        if ( !defined( 'PODS_DISABLE_EVAL' ) || !PODS_DISABLE_EVAL )
            eval( "?>$code" );
        else
            echo $code;

        $out = ob_get_clean();
        $out = preg_replace_callback( '/({@(.*?)})/m', array( 'self', 'do_magic_tags' ), $out );

        return apply_filters( 'pods_templates_do_template', $out, $code, $obj );
    }

    /**
     * Replace magic tags with their values
     *
     * @param string $tag The magic tag to evaluate
     * @param object $obj The Pods object
     *
     * @since 1.x
     */
    public static function do_magic_tags ( $tag, $obj = null ) {
        if ( !empty( $obj ) )
            self::$obj =& $obj;
        else
            $obj =& self::$obj;

        if ( empty( $obj ) || !is_object( $obj ) )
            return '';

        if ( is_array( $tag ) ) {
            if ( !isset( $tag[ 2 ] ) && strlen( trim( $tag[ 2 ] ) ) < 1 )
                return;

            $tag = $tag[ 2 ];
        }

        $tag = trim( $tag, ' {@}' );
        $tag = explode( ',', $tag );

        if ( empty( $tag ) || !isset( $tag[ 0 ] ) || strlen( trim( $tag[ 0 ] ) ) < 1 )
            return;

        foreach ( $tag as $k => $v ) {
            $tag[ $k ] = trim( $v );
        }

        $field_name = $tag[ 0 ];

        if ( 'type' == $field_name )
            $value = $obj->pod;
        else
            $value = $obj->field( $field_name );

        $helper_name = $before = $after = '';

        if ( isset( $tag[ 1 ] ) && !empty( $tag[ 1 ] ) ) {
            $helper_name = $tag[ 1 ];

            $params = array(
                'helper' => $helper_name,
                'value' => $value,
                'name' => $field_name
            );

            if ( class_exists( 'Pods_Helpers' ) )
                $value = Pods_Helpers::helper( $params, $obj );
        }

        if ( isset( $tag[ 2 ] ) && !empty( $tag[ 2 ] ) )
            $before = $tag[ 2 ];

        if ( isset( $tag[ 3 ] ) && !empty( $tag[ 3 ] ) )
            $after = $tag[ 3 ];

        $value = apply_filters( 'pods_templates_do_magic_tags', $value, $field_name, $helper_name, $before, $after );

        if ( is_array( $value ) )
            $value = pods_serial_comma( $value, $field_name, $obj->fields );

        if ( null !== $value && false !== $value )
            return $before . $value . $after;

        return;

    }
}
