<template>
    <div id="content" :class="{ 'nav-hidden': false, 'sidebar-hidden': false }">
        <AppNavigation
            :projects="projects"
            :selectedProjectId="currentProjectId"
            @projectClicked="onProjectClicked"
            @newBillClicked="onNewBillClicked"
            @qrcodeClicked="onQrcodeClicked"
            @statsClicked="onStatsClicked"
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
                    @itemDeleted="onBillDeleted"
                />
                <BillForm
                    v-if="currentBill !== null && mode === 'edition'"
                    :bill="currentBill"
                    @billCreated="onBillCreated"
                    @billSaved="onBillSaved"
                />
                <MoneyBusterLink
                    v-if="mode === 'qrcode'"
                    :project="currentProject"
                />
                <Statistics
                    v-if="mode === 'stats'"
                    :projectId="currentProjectId"
                />
            </div>
        </div>
        <!--router-view name="sidebar" /-->
        <img id="dummylogo"/>
    </div>
</template>

<script>
import AppNavigation from './components/AppNavigation'
import BillForm from './BillForm';
import BillList from './BillList';
import MoneyBusterLink from './MoneyBusterLink';
import Statistics from './Statistics';
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
        BillForm,
        MoneyBusterLink,
        Statistics
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
        currentProject: function() {
            return this.projects[this.currentProjectId];
        },
        selectedBillId: function() {
            return (this.currentBill !== null) ? this.currentBill.id : -1;
        },
        currentBills: function() {
            console.log('[APP] get current bill list '+this.currentProjectId)
            return (this.currentProjectId && this.billLists.hasOwnProperty(this.currentProjectId)) ? this.billLists[this.currentProjectId] : [];
        },
        defaultPayerId: function() {
            let payerId = -1;
            const members = this.members[this.currentProjectId];
            if (members && Object.keys(members).length > 0) {
                if (cospend.pageIsPublic) {
                    payerId = Object.keys(members)[0];
                } else {
                    payerId = Object.keys(members)[0];
                    let member;
                    for (const mid in members) {
                        member = members[mid];
                        if (member.userid === getCurrentUser().uid) {
                            payerId = member.id;
                        }
                    }
                }
            }
            return payerId;
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
            this.updateBalances(cospend.currentProjectId);
        },
        onBillSaved: function(bill) {
            this.updateBalances(cospend.currentProjectId);
        },
        onBillDeleted: function(bill) {
            const billList = this.billLists[cospend.currentProjectId];
            billList.splice(billList.indexOf(bill), 1);
            if (bill.id === this.selectedBillId) {
                this.currentBill = null;
            }
            this.updateBalances(cospend.currentProjectId);
        },
        onProjectClicked: function(projectid) {
            this.selectProject(projectid);
        },
        onQrcodeClicked: function(projectid) {
            if (cospend.currentProjectId !== projectid) {
                this.selectProject(projectid);
            }
            this.mode = 'qrcode';
        },
        onStatsClicked: function(projectid) {
            if (cospend.currentProjectId !== projectid) {
                this.selectProject(projectid);
            }
            this.mode = 'stats';
        },
        selectProject: function(projectid, save=true) {
            this.getBills(projectid);
            if (save) {
                saveOptionValue({selectedProject: projectid});
            }
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
                const payer_id = this.defaultPayerId;
                this.currentBill = {
                    id: 0,
                    what: '',
                    timestamp: moment().unix(),
                    amount: 0.0,
                    payer_id: payer_id,
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
            this.mode = 'edition';
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
            this.mode = 'edition';
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
                            cospend.members[proj.id][proj.members[i].id] = proj.members[i];
                            that.$set(that.members[proj.id], proj.members[i].id, proj.members[i]);
                            //proj.members[i].balance = proj.balance[proj.members[i].id];
                            that.$set(that.members[proj.id][proj.members[i].id], 'balance', proj.balance[proj.members[i].id]);
                            //proj.members[i].color = rgbObjToHex(proj.members[i].color).replace('#', '');
                            that.$set(that.members[proj.id][proj.members[i].id], 'color', rgbObjToHex(proj.members[i].color).replace('#', ''));
                        }

                        cospend.bills[proj.id] = {};
                        that.$set(that.bills, proj.id, cospend.bills[proj.id]);

                        cospend.billLists[proj.id] = [];
                        that.$set(that.billLists, proj.id, cospend.billLists[proj.id]);
                        //that.$set(cospend.projects, proj.id, proj);
                    }
                    if (cospend.restoredCurrentProjectId !== null) {
                        that.selectProject(cospend.restoredCurrentProjectId, false);
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
        updateBalances: function(projectid) {
            const that = this;
            const req = {};
            let url;
            if (!cospend.pageIsPublic) {
                url = generateUrl('/apps/cospend/projects/' + projectid);
            } else {
                url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password);
            }
            cospend.currentGetProjectsAjax = $.ajax({
                type: 'GET',
                url: url,
                data: req,
                async: true,
            }).done(function(response) {
                let balance;
                for (const memberid in response.balance) {
                    balance = response.balance[memberid];
                    //that.members[projectid][memberid].balance = balance;
                    console.log('update '+that.members[projectid][memberid].name+' to '+balance)
                    that.$set(that.members[projectid][memberid], 'balance', balance);
                }
            }).always(function() {
            }).fail(function() {
                Notification.showTemporary(t('cospend', 'Failed to update balances'));
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