import Vue from 'vue'
import QRCode from './components/QRCode'
/**
 * @class OCA.Approval.ApprovalInfoView
 * @classdesc
 *
 * Displays a approval buttons
 *
 */
export const ApprovalInfoView = OCA.Files.DetailFileInfoView.extend(
	/** @lends OCA.Approval.ApprovalInfoView.prototype */ {

		_rendered: false,

		className: 'approvalInfoView',
		name: 'approval',

		/* required by the new files sidebar to check if the view is unique */
		id: 'approvalInfoView',

		_inputView: null,

		filename: '',

		initialize(options) {
			options = options || {}

			this._inputView = {
				$el: document.createElement('div'),
			}

			// this._inputView.collection.on('change:name', this._onTagRenamedGlobally, this)
			// this._inputView.collection.on('remove', this._onTagDeletedGlobally, this)

			// this._inputView.on('select', this._onSelectTag, this)
			// this._inputView.on('deselect', this._onDeselectTag, this)
		},

		_onApprove() {
			// TODO
		},

		setFileInfo(fileInfo) {
			console.debug('setFileInfo')
			console.debug(fileInfo)
			// Why is this called twice and fileInfo is not the same on each call?
			this.filename = fileInfo.name || fileInfo.attributes?.name || ''

			if (!this._rendered) {
				this.render()
			}
			// this.hide()
		},

		/**
		 * Renders this details view
		 */
		render() {
			const View = Vue.extend(QRCode)
			this._inputView = new View({
				propsData: { link: this.filename },
			}).$mount(this._inputView.$el)
			this.$el.append(this._inputView.$el)
		},

		isVisible() {
			return !this.$el.hasClass('hidden')
		},

		show() {
			this.$el.removeClass('hidden')
		},

		hide() {
			this.$el.addClass('hidden')
		},

		toggle() {
			this.$el.toggleClass('hidden')
		},

		remove() {
			this._inputView.remove()
		},
	})
