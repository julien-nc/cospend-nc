<template>
	<div>
		<Multiselect
			v-if="true"
			v-model="selectedSharee"
            class="shareInput"
			:placeholder="t('cospend', 'Share project with a user, group or circle …')"
			:options="formatedSharees"
			:user-select="true"
			label="displayName"
			track-by="multiselectKey"
			:internal-search="true"
			@input="clickShareeItem"
            />

		<!--ul
			id="shareWithList"
			class="shareWithList">
			<li>
				<Avatar :user="board.owner.uid" />
				<span class="has-tooltip username">
					{{ board.owner.displayname }}
					<span v-if="!isCurrentUser(board.owner.uid)" class="board-owner-label">
						{{ t('deck', 'Board owner') }}
					</span>
				</span>
			</li>
			<li v-for="acl in board.acl" :key="acl.id">
				<Avatar v-if="acl.type===0" :user="acl.participant.uid" />
				<div v-if="acl.type===1" class="avatardiv icon icon-group" />
				<div v-if="acl.type===7" class="avatardiv icon icon-circles" />
				<span class="has-tooltip username">
					{{ acl.participant.displayname }}
					<span v-if="acl.type===1">{{ t('deck', '(Group)') }}</span>
					<span v-if="acl.type===7">{{ t('deck', '(Circle)') }}</span>
				</span>

				<ActionCheckbox v-if="!(isCurrentUser(acl.participant.uid) && acl.type === 0) && (canManage || (canEdit && canShare))" :checked="acl.permissionEdit" @change="clickEditAcl(acl)">
					{{ t('deck', 'Can edit') }}
				</ActionCheckbox>
				<Actions v-if="!(isCurrentUser(acl.participant.uid) && acl.type === 0)" :force-menu="true">
					<ActionCheckbox v-if="canManage || canShare" :checked="acl.permissionShare" @change="clickShareAcl(acl)">
						{{ t('deck', 'Can share') }}
					</ActionCheckbox>
					<ActionCheckbox v-if="canManage" :checked="acl.permissionManage" @change="clickManageAcl(acl)">
						{{ t('deck', 'Can manage') }}
					</ActionCheckbox>
					<ActionButton v-if="canManage" icon="icon-delete" @click="clickDeleteAcl(acl)">
						{{ t('deck', 'Delete') }}
					</ActionButton>
				</Actions>
			</li>
		</ul>

		<CollectionList v-if="board.id"
			:id="`${board.id}`"
			:name="board.title"
			type="deck" /-->
	</div>
</template>

<script>
import { Avatar, Multiselect, Actions, ActionButton, ActionCheckbox } from '@nextcloud/vue'
//import { CollectionList } from 'nextcloud-vue-collections'
import { mapGetters, mapState } from 'vuex'
import { getCurrentUser } from '@nextcloud/auth'
import {generateUrl} from '@nextcloud/router';
import {
    showSuccess,
    showError,
} from '@nextcloud/dialogs'
import cospend from '../state';

export default {
	name: 'SharingTabSidebar',
	components: {
		Avatar,
		Actions,
		ActionButton,
		ActionCheckbox,
		Multiselect,
	},
	props: ['projectId'],
	data() {
		return {
			isLoading: false,
			selectedSharee: null,
            addAclForAPI: null,
            sharees: [],
		}
	},
	computed: {
		isCurrentUser() {
			return (uid) => uid === getCurrentUser().uid
		},
		formatedSharees() {
			return this.unallocatedSharees.map(item => {
				const sharee = {
					user: item.id,
					displayName: item.label,
                    icon: 'icon-user',
                    type: item.type,
                    value: item.value,
					multiselectKey: item.type + ':' + item.id,
				}
				if (item.type === 'g') {
					sharee.icon = 'icon-group'
					sharee.isNoUser = true
				}
				if (item.type === 'c') {
					sharee.icon = 'icon-circles'
					sharee.isNoUser = true
				}
				return sharee
			})
        },
        // those with which the project is not shared yet
		unallocatedSharees() {
			return this.sharees.filter((sharee) => {
                return true;
				//const foundIndex = this.board.acl.findIndex((acl) => {
				//	return acl.participant.uid === sharee.value.shareWith && acl.participant.type === sharee.value.shareType
				//})
				//if (foundIndex === -1) {
				//	return true
				//}
				//return false
			})
		},
	},
	mounted() {
		this.asyncFind('')
	},
	methods: {
		asyncFind(query) {
            this.isLoading = true
            this.loadSharees(query);
        },
        loadSharees() {
            const that = this;
            const url = generateUrl('/apps/cospend/user-list');
            $.ajax({
                type: 'GET',
                url: url,
                data: {},
                async: true
            }).done(function(response) {
                cospend.userIdName = response.users;
                cospend.groupIdName = response.groups;
                cospend.circleIdName = response.circles;
                const data = [];
                let d, name, id;
                for (id in response.users) {
                    name = response.users[id];
                    d = {
                        id: id,
                        name: name,
                        type: 'u',
                    };
                    if (id !== name) {
                        d.label = name + ' (' + id + ')';
                        d.value = name + ' (' + id + ')';
                    } else {
                        d.label = name;
                        d.value = name;
                    }
                    data.push(d);
                }
                for (id in response.groups) {
                    name = response.groups[id];
                    d = {
                        id: id,
                        name: name,
                        type: 'g',
                    };
                    if (id !== name) {
                        d.label = name + ' (' + id + ')';
                        d.value = name + ' (' + id + ')';
                    } else {
                        d.label = name;
                        d.value = name;
                    }
                    data.push(d);
                }
                for (id in response.circles) {
                    name = response.circles[id];
                    d = {
                        id: id,
                        name: name,
                        type: 'c',
                    };
                    d.label = name;
                    d.value = name;
                    data.push(d);
                }
                that.sharees = data;
            }).fail(function() {
                showError(t('cospend', 'Failed to get user list.'));
            });
        },
		clickShareeItem() {
            console.log(this.selectedSharee);
            //this.selectedSharee = null;
            return;
			this.addAclForAPI = {
				type: this.selectedSharee.type,
				participant: this.selectedSharee.value.shareWith,
				permissionEdit: false,
				permissionShare: false,
				permissionManage: false,
			}
			//this.$store.dispatch('addAclToCurrentBoard', this.addAclForAPI)
		},
		/*clickEditAcl(acl) {
			this.addAclForAPI = Object.assign({}, acl)
			this.addAclForAPI.permissionEdit = !acl.permissionEdit
			this.$store.dispatch('updateAclFromCurrentBoard', this.addAclForAPI)
		},
		clickShareAcl(acl) {
			this.addAclForAPI = Object.assign({}, acl)
			this.addAclForAPI.permissionShare = !acl.permissionShare
			this.$store.dispatch('updateAclFromCurrentBoard', this.addAclForAPI)
		},
		clickManageAcl(acl) {
			this.addAclForAPI = Object.assign({}, acl)
			this.addAclForAPI.permissionManage = !acl.permissionManage
			this.$store.dispatch('updateAclFromCurrentBoard', this.addAclForAPI)
		},
		clickDeleteAcl(acl) {
			this.$store.dispatch('deleteAclFromCurrentBoard', acl)
		},*/
	},
}
</script>
<style scoped>
    .shareInput {
        width: 100%;
    }
	#shareWithList {
		margin-bottom: 20px;
	}
	#shareWithList li {
		display: flex;
		align-items: center;
	}
	.username {
		padding: 12px 9px;
		flex-grow: 1;
	}
	.board-owner-label {
		opacity: .7;
	}
	.avatarLabel {
		padding: 6px
	}
	.avatardiv {
		background-color: #f5f5f5;
		border-radius: 16px;
		width: 32px;
		height: 32px;
	}
</style>