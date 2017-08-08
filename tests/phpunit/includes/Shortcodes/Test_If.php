<?php
namespace Pods_Unit_Tests\Shortcodes;

/**
 * Class Test_If
 * @package Pods_Unit_Tests
 * @group pods_acceptance_tests
 * @group pods-shortcodes
 * @group pods-shortcodes-if
 */
class Tests_If extends \Pods_Unit_Tests\Pods_UnitTestCase {

	protected static $pod_name = 'test_if';

	protected static $pod_id;

	static public function setUpBeforeClass() {
		parent::setupBeforeClass();
		add_shortcode( 'test_if_text', function( $args, $content ) { return 'abc123'; } );
		add_shortcode( 'test_if_recurse', function( $args, $content ) { return do_shortcode( $content ); } );

		self::$pod_id = pods_api()->save_pod( array( 'storage' => 'meta', 'type' => 'post_type', 'name' => self::$pod_name ) );

		$params = array(
			'pod'    => self::$pod_name,
			'pod_id' => self::$pod_id,
			'name'   => 'number1',
			'type'   => 'number'
		);
		pods_api()->save_field( $params );
		$params = array(
			'pod'    => self::$pod_name,
			'pod_id' => self::$pod_id,
			'name'   => 'number2',
			'type'   => 'number'
		);
		pods_api()->save_field( $params );
	}

	static public function tearDownAfterClass() {
		parent::tearDownAfterClass();
		if ( shortcode_exists( 'test_if_text') ) {
			remove_shortcode( 'test_if_text' );
		}
		if ( shortcode_exists( 'test_if_recurse') ) {
			remove_shortcode( 'test_if_recurse' );
		}
		pods_api()->delete_pod( array( 'id' => self::$pod_id ) );

	}

	public function test_psuedo_shortcodes() {
		// Make sure our pseudo shortcodes are working properly
		$this->assertEquals( 'abc123', do_shortcode( '[test_if_text]' ) );
		$this->assertEquals( 'abc123', do_shortcode( '[test_if_recurse][test_if_text][/test_if_recurse]' ) );
	}

	public function test_if_simple() {
		$pod_name = self::$pod_name;
		$id = pods( $pod_name )->add( array( 'name' => __FUNCTION__ . '1', 'number1' => 123, 'number2' => 456 ) );
		$content = base64_encode( 'ABC' );
		$this->assertEquals( 'ABC', do_shortcode( "[pod_if_field pod='{$pod_name}' id='{$id}' field='number1']{$content}[/pod_if_field]" ) );
		$content = base64_encode( 'ABC[else]DEF' );
		$this->assertEquals( 'ABC', do_shortcode( "[pod_if_field pod='{$pod_name}' id='{$id}' field='number1']{$content}[/pod_if_field]" ) );
		$this->assertNotEquals( 'DEF', do_shortcode( "[pod_if_field pod='{$pod_name}' id='{$id}' field='number1']{$content}[/pod_if_field]" ) );

		$id = pods( $pod_name )->add( array( 'name' => __FUNCTION__ . '2', 'number1' => 456 ) );
		$content = base64_encode( 'ABC' );
		$this->assertNotEquals( 'ABC', do_shortcode( "[pod_if_field pod='{$pod_name}' id='{$id}' field='number2']{$content}[/pod_if_field]" ) );
		$this->assertNotEquals( 'ABC', do_shortcode( "[pod_if_field pod='{$pod_name}' id='{$id}' field='invalidfield']{$content}[/pod_if_field]" ) );
		$content = base64_encode( 'ABC[else]DEF' );
		$this->assertEquals( 'DEF', do_shortcode( "[pod_if_field pod='{$pod_name}' id='{$id}' field='number2']{$content}[/pod_if_field]" ) );
		$this->assertEquals( 'DEF', do_shortcode( "[pod_if_field pod='{$pod_name}' id='{$id}' field='invalidfield']{$content}[/pod_if_field]" ) );
		$this->assertNotEquals( 'ABC', do_shortcode( "[pod_if_field pod='{$pod_name}' id='{$id}' field='number2']{$content}[/pod_if_field]" ) );
	}

	public function test_if_nested() {
		$pod_name = self::$pod_name;
		$id = pods( $pod_name )->add( array( 'name' => __FUNCTION__ . '1', 'number1' => 123, 'number2' => 456 ) );
		$inner_content = base64_encode( 'XYZ' );
		$content = base64_encode( "[pod_if_field pod='{$pod_name}' id='{$id}' field='number2']{$inner_content}[/pod_if_field]" );
		$this->assertEquals( 'XYZ', do_shortcode( "[pod_if_field pod='{$pod_name}' id='{$id}' field='number1']{$content}[/pod_if_field]" ) );

		$inner_content = base64_encode( 'XYZ' );
		$content = base64_encode( "[pod_if_field pod='{$pod_name}' id='{$id}' field='number2']{$inner_content}[/pod_if_field]" );
		$this->assertEquals( 'XYZ', do_shortcode( "[test_if_recurse][pod_if_field pod='{$pod_name}' id='{$id}' field='number1']{$content}[/pod_if_field][/test_if_recurse]" ) );

		$this->markTestIncomplete( 'Nested shortcodes currently broken, test disabled until issue resolved' );
		$inner_content = base64_encode( '[test_if_recurse]XYZ[/test_if_recurse]' );
		$content = base64_encode( "[pod_if_field pod='{$pod_name}' id='{$id}' field='number2']{$inner_content}[/pod_if_field]" );
		$this->assertEquals( 'XYZ', do_shortcode( "[pod_if_field pod='{$pod_name}' id='{$id}' field='number1']{$content}[/pod_if_field]" ) );

	}

	public function test_if_with_magic_tags() {
		$pod_name = self::$pod_name;
		$id = pods( $pod_name )->add( array( 'name' => 'my post title', 'number1' => 123, 'number2' => 456 ) );
		$content = base64_encode( '{@post_title}' );
		$this->assertEquals( 'my post title', do_shortcode( "[pod_if_field pod='{$pod_name}' id='{$id}' field='number1']{$content}[/pod_if_field]" ) );
		$content = base64_encode( '{@number1}' );
		$this->assertEquals( '123', do_shortcode( "[pod_if_field pod='{$pod_name}' id='{$id}' field='number1']{$content}[/pod_if_field]" ) );

		$id = pods( $pod_name )->add( array( 'name' => 'my post title', 'number1' => 456 ) );
		$content = base64_encode( '{@number2}[else]{@number1}' );
		$this->assertEquals( '456', do_shortcode( "[pod_if_field pod='{$pod_name}' id='{$id}' field='number2']{$content}[/pod_if_field]" ) );
	}
}