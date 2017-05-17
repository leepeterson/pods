/*global jQuery, _, Backbone, Marionette, select2, sprintf, wp, ajaxurl, PodsI18n */
// Note: this is a template-less view
import {PodsFieldListView, PodsFieldView} from 'pods-dfv/_src/core/pods-field-views';
import {RelationshipCollection} from 'pods-dfv/_src/pick/relationship-model';

const SELECT2_DEBOUNCE_DELAY = 300;
const SELECT2_AJAX_MINIMUM_INPUT_LENGTH = 1;
const SELECT2_UL_TARGET = 'ul.select2-selection__rendered';
const SELECT2_SELECTED_TARGET = '.select2-selection__choice';

/**
 * option
 *
 * @extends Backbone.View
 */
export const SelectItem = PodsFieldView.extend( {
	tagName: 'option',

	template: false,

	initialize: function ( options ) {
		this.$el.val( this.model.get( 'id' ) );

		this.$el.html( this.model.get( 'name' ) );

		if ( this.model.get( 'selected' ) ) {
			this.$el.prop( 'selected', 'selected' );
		}
	}
} );

/**
 * optgroup
 *
 * @extends Backbone.View
 */
export const Optgroup = PodsFieldListView.extend( {
	tagName  : 'optgroup',
	childView: SelectItem,

	attributes: function () {
		return {
			label: this.model.get( 'label' )
		};
	}
} );

/**
 * select
 *
 * @extends Backbone.View
 */
export const SelectView = Marionette.CollectionView.extend( {
	tagName: 'select',

	triggers: {
		"change": {
			event          : "change:selected",
			stopPropagation: false
		}
	},

	initialize: function ( options ) {
		this.fieldModel = options.fieldModel;
		this.fieldConfig = this.fieldModel.get( 'fieldConfig' );
	},

	/**
	 * Set the proper child view (optgroups or no)
	 *
	 * @param item
	 * @returns {*}
	 */
	childView: function ( item ) {
		if ( this.fieldConfig.optgroup ) {
			return Optgroup;
		}
		else {
			return SelectItem;
		}
	},

	/**
	 * todo: We're bypassing the PodsFieldListView functionality, need to explicitly include it for now
	 *
	 * @param model
	 * @param index
	 * @returns {{fieldModel: *}}
	 */
	childViewOptions: function ( model, index ) {
		let returnOptions = { fieldModel: this.fieldModel };

		if ( this.fieldConfig.optgroup ) {
			returnOptions.collection = new RelationshipCollection( model.get( 'collection' ) );
		}

		return returnOptions;
	},

	/**
	 * todo: We're bypassing the PodsFieldListView functionality, need to explicitly include it for now
	 *
	 * @returns {{}}
	 */
	serializeData: function () {
		const fieldModel = this.options.fieldModel;
		let data = this.model ? this.model.toJSON() : {};

		data.htmlAttr = fieldModel.get( 'attributes' );
		data.fieldConfig = fieldModel.get( 'fieldConfig' );

		return data;
	},

	/**
	 *
	 */
	attributes: function () {

		/**
		 * @param {string} htmlAttr.name
		 * @param {string} htmlAttr.class
		 * @param {string} htmlAttr.name_clean
		 * @param {string} htmlAttr.id
		 *
		 * @param {string} fieldConfig.pick_format_type 'single' or 'multi'
		 */
		const fieldModel = this.options.fieldModel;
		const htmlAttr = fieldModel.get( 'htmlAttr' );
		const fieldConfig = fieldModel.get( 'fieldConfig' );

		let name = htmlAttr.name;
		if ( fieldConfig.pick_format_type === 'multi' ) {
			name = name + '[]';
		}
		return {
			'name'           : name,
			'class'          : htmlAttr.class,
			'data-name-clean': htmlAttr.name_clean,
			'id'             : htmlAttr.id,
			'tabindex'       : '2',
			'multiple'       : ( fieldConfig.pick_format_type === 'multi' )
		};
	},

	/**
	 * Setup to be done once attached to the DOM.  Select2 has some setup needs.
	 */
	onAttach: function () {

		if ( this.fieldConfig.view_name === 'select2' ) {
			this.setupSelect2();
		}
	},

	/**
	 *
	 */
	onChangeSelected: function () {
		this.collection.setSelected( this.$el.val() );
	},

	/**
	 * No filtering, by default.  Consuming views can override this function to provide custom filtering
	 * (e.g. List View needs to filter items already selected for its select from existing list)
	 *
	 * @param data
	 */
	filterAjaxList: function ( data ) {
		return data;
	},

	/**
	 * Initialize Select2, setup drag-drop reordering
	 */
	setupSelect2: function () {
		const self = this;
		const $select2 = this.$el;
		const fieldConfig = this.options.fieldModel.get( 'fieldConfig' );
		const ajaxData = fieldConfig.ajax_data;
		const limit = fieldConfig.pick_limit;
		let $ulContainer, select2Options, placeholder;

		if ( fieldConfig.limitDisable ) {
			placeholder = `${PodsI18n.__( 'You can only select' )} ${sprintf( PodsI18n._n( '%s item', '%s items', limit ), limit )}`;
		} else {
			placeholder = `${PodsI18n.__( 'Search' )} ${fieldConfig.label}...`
		}

		select2Options = {
			maximumSelectionLength: limit,
			placeholder           : placeholder,
			allowClear            : false,
			disabled              : fieldConfig.limitDisable
		};

		if ( ajaxData.ajax ) {
			jQuery.extend( select2Options, {
				minimumInputLength: SELECT2_AJAX_MINIMUM_INPUT_LENGTH,
				ajax              : {
					url           : ajaxurl + '?pods_ajax=1',
					type          : 'POST',
					dataType      : 'json',
					delay         : SELECT2_DEBOUNCE_DELAY,
					data          : function ( params ) {
						return {
							_wpnonce: ajaxData._wpnonce,
							action  : 'pods_relationship',
							method  : 'select2',
							pod     : ajaxData.pod,
							field   : ajaxData.field,
							uri     : ajaxData.uri,
							id      : ajaxData.id,
							query   : params.term // ToDo: term{lang}
						};
					},
					processResults: function ( data, params ) {
						return self.filterAjaxList( data, params );
					}
				}
			} );
		}

		// Initialize select2
		$select2.select2( select2Options );

		// Get a reference to the ul container of the visual UI portion.  Can't do this until select2 is initialized
		$ulContainer = $select2.parent().find( SELECT2_UL_TARGET );

		// Make the list drag-drop sortable
		$ulContainer.sortable( {
			containment: 'parent'
		} );

		// With select2 4.0, sortable is just reordering the UI elements.  Keep the underlying select/option list
		// synced with the changes.  See: https://github.com/select2/select2/issues/3004
		$ulContainer.on( 'sortstop', function () {
			const $selected = $ulContainer.find( SELECT2_SELECTED_TARGET ).get().reverse();

			jQuery( $selected ).each( function () {
				const id = jQuery( this ).data( 'data' ).id;
				const option = $select2.find( 'option[value="' + id + '"]' )[ 0 ];

				$select2.prepend( option );
			} );
		} );
	}

} );