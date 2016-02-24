/*global jQuery, _, Backbone, Mn, wp */
import * as formTemplate from './file-upload-form.html';
import { PodsFieldListView, PodsFieldView } from '../../../_src/core/pods-field-views';

export const FileUploadForm = PodsFieldView.extend( {
	tagName: 'div',

	ui: {
		addButton: '.pods-file-add'
	},

	template: _.template( formTemplate.default ),

	triggers: {
		'click @ui.addButton': 'add:file:click'
	}
} );
