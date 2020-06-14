<template>
	<div id="content" :class="{ 'nav-hidden': false, 'sidebar-hidden': false }">
		<AppNavigation
            :projects="projects"
            @projectClicked="onProjectClicked"
            @newBillClicked="onNewBillClicked"
        />
		<div id="app-content">
            <div id="app-content-wrapper">
                <BillList
                    :loading="billsLoading"
                    :projectId="currentProjectId"
                    :bills="currentBills"
                    :selectedBillId="selectedBillId"
                    :editionAccess="true"
                    @itemClicked="onBillClicked"
                />
                <BillForm
                    v-if="currentBill !== null && mode === 'edition'"
                    :bill="currentBill"
                    @billCreated="onBillCreated"
                />
            </div>
		</div>
		<!--router-view name="sidebar" /-->
	</div>
</template>

<script>
import AppNavigation from './components/AppNavigation'
import BillForm from './BillForm';
import BillList from './BillList';
import cospend from './state';
import {generateUrl} from '@nextcloud/router';
import {getCurrentUser} from '@nextcloud/auth';
import * as Notification from './notification';
import * as constants from './constants';
import {rgbObjToHex, saveOptionValue} from './utils';

export default {
	name: 'App',
	components: {
        AppNavigation,
        BillList,
        BillForm
	},
	data: function() {
		return {
            mode: 'edition',
            cospend: cospend,
            projects: {},
            bills: {},
            billLists: {},
            members: {},
            billsLoading: false,
            currentBill: null
		}
	},
	computed: {
        currentProjectId: function() {
            return this.cospend.currentProjectId;
        },
        selectedBillId: function() {
            return (this.currentBill !== null) ? this.currentBill.id : -1;
        },
        currentBills: function() {
            console.log('[APP] get current bill list '+this.currentProjectId)
            return (this.currentProjectId && this.billLists.hasOwnProperty(this.currentProjectId)) ? this.billLists[this.currentProjectId] : [];
        },
	},
	provide: function() {
		return {
		}
	},
	created: function() {
        this.getProjects();
    },
    mounted() {
        // once this is done, it becomes reactive...
        //this.$set(this.cospend, 'selectedBillId', -1);
    },
    methods: {
        cleanupBills: function() {
            const billList = this.billLists[cospend.currentProjectId];
            for (let i = 0; i < billList.length; i++) {
                if (billList[i].id === 0) {
                    billList.splice(i, 1);
                    break;
                }
            }
        },
        onBillCreated: function(bill, select) {
            this.bills[cospend.currentProjectId][bill.id] = bill;
            this.billLists[cospend.currentProjectId].push(bill);
            this.cleanupBills();
            if (select) {
                this.currentBill = bill;
            }
        },
        onProjectClicked: function(projectid) {
            console.log('[APP] on project clicked')
            this.getBills(projectid);
            saveOptionValue({selectedProject: projectid});
            this.currentBill = null;
            cospend.currentProjectId = projectid;
        },
        onNewBillClicked: function() {
            // find potentially existing new bill
            const billList = this.billLists[cospend.currentProjectId];
            let found = -1;
            for (let i = 0; i < billList.length; i++) {
                if (billList[i].id === 0) {
                    found = i;
                    break;
                }
            }
            if (found === -1) {
                this.currentBill = {
                    id: 0,
                    what: '',
                    timestamp: moment().unix(),
                    amount: 0.0,
                    payer_id: 0,
                    repeat: 'n',
                    owers: [],
                    owerIds: [],
                    paymentmode: 'n',
                    categoryid: 0,
                    comment: ''
                };
                this.billLists[cospend.currentProjectId].push(this.currentBill);
            } else {
                this.currentBill = billList[found];
            }
            // select new bill in case it was not selected yet
            //this.selectedBillId = billid;
        },
        onBillClicked: function(billid) {
            const billList = this.billLists[cospend.currentProjectId];
            if (billid === 0) {
                let found = -1;
                for (let i = 0; i < billList.length; i++) {
                    if (billList[i].id === 0) {
                        found = i;
                        break;
                    }
                }
                this.currentBill = billList[found];
            } else {
                this.currentBill = this.bills[cospend.currentProjectId][billid];
            }
        },
        getProjects: function() {
            const that = this;
            const req = {};
            let url;
            if (!cospend.pageIsPublic) {
                url = generateUrl('/apps/cospend/projects');
            } else {
                url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password);
            }
            $.ajax({
                type: 'GET',
                url: url,
                data: req,
                async: true
            }).done(function(response) {
                console.log('public ? '+cospend.pageIsPublic)
                if (!cospend.pageIsPublic) {
                    //cospend.bills = {};
                    //cospend.billLists = {};
                    //cospend.members = {};
                    //cospend.projects = {};
                    let proj;
                    for (let i = 0; i < response.length; i++) {
                        proj = response[i];

                        cospend.projects[proj.id] = proj;
                        that.$set(that.projects, proj.id, proj);

                        cospend.members[proj.id] = {};
                        that.$set(that.members, proj.id, cospend.members[proj.id]);
                        for (let i = 0; i < proj.members.length; i++) {
                            proj.members[i].color = rgbObjToHex(proj.members[i].color).replace('#', '');
                            cospend.members[proj.id][proj.members[i].id] = proj.members[i];
                            that.$set(that.members[proj.id], proj.members[i].id, proj.members[i]);
                        }

                        cospend.bills[proj.id] = {};
                        that.$set(that.bills, proj.id, cospend.bills[proj.id]);

                        cospend.billLists[proj.id] = [];
                        that.$set(that.billLists, proj.id, cospend.billLists[proj.id]);
                        //that.$set(cospend.projects, proj.id, proj);
                    }
                    console.log('zzz')
                    console.log(cospend.restoredCurrentProjectId)
                    if (cospend.restoredCurrentProjectId !== null) {
                        cospend.currentProjectId = cospend.restoredCurrentProjectId;
                        that.getBills(cospend.currentProjectId);
                    }
                } else {
                    if (!response.myaccesslevel) {
                        response.myaccesslevel = response.guestaccesslevel;
                    }
                    //addProject(response);
                    that.projects[response.id] = response;
                    //that.$set(cospend.projects, response.id, response);
                    cospend.currentProjectId = cospend.projectid;
                }
            }).always(function() {
            }).fail(function(response) {
                Notification.showTemporary(t('cospend', 'Failed to get projects') +
                    ': ' + (response.responseJSON)
                );
            });
        },
        getBills: function(projectid) {
            this.billsLoading = true;
            const that = this;
            const req = {};
            let url;
            if (!cospend.pageIsPublic) {
                url = generateUrl('/apps/cospend/projects/' + projectid + '/bills');
            } else {
                url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/bills');
            }
            cospend.currentGetProjectsAjax = $.ajax({
                type: 'GET',
                url: url,
                data: req,
                async: true,
            }).done(function(response) {
                that.bills[projectid] = {};
                //that.billLists[projectid] = response;
                that.$set(that.billLists, projectid, response);
                let bill;
                for (let i = 0; i < response.length; i++) {
                    bill = response[i];
                    that.bills[projectid][bill.id] = bill;
                }
            }).always(function() {
                that.billsLoading = false;
            }).fail(function() {
                Notification.showTemporary(t('cospend', 'Failed to get bills'));
            });
        },
    }
}
</script>

<style lang="scss" scoped>
	#content {
		#app-content {
			transition: margin-left 100ms ease;
			position: relative;
			overflow-x: hidden;
			align-items: stretch;
		}
		#app-sidebar {
			transition: max-width 100ms ease;
		}
		&.nav-hidden {
			#app-content {
				margin-left: 0;
			}
		}
		&.sidebar-hidden {
			#app-sidebar {
				max-width: 0;
				min-width: 0;
			}
		}
	}
</style>

<style>
	#content * {
		box-sizing: border-box;
	}
</style>