/*
 * Copyright (c) 2021 Julien Veyssier <eneiluj@posteo.net>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */
import { ApprovalInfoView } from './approvalinfoview'

(function() {
	if (!OCA.Approval) {
		/**
		 * @namespace
		 */
		OCA.Approval = {}
	}

	/**
	 * @namespace
	 */
	OCA.Approval.FilesPlugin = {
		ignoreLists: [
			'trashbin',
			'files.public',
		],

		attach(fileList) {
			if (this.ignoreLists.indexOf(fileList.id) >= 0) {
				return
			}

			const approvalInfoView = new ApprovalInfoView()
			fileList.registerDetailView(approvalInfoView)
			OCA.Approval.View = approvalInfoView
		},
	}

})()

OC.Plugins.register('OCA.Files.FileList', OCA.Approval.FilesPlugin)
