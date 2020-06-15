<template>
    <!--div id="content" :class="{ 'nav-hidden': false, 'sidebar-hidden': false }"-->
    <Content app-name="Cospend">
        <AppNavigation
            :projects="projects"
            :selectedProjectId="currentProjectId"
            @projectClicked="onProjectClicked"
            @deleteProject="onDeleteProject"
            @newBillClicked="onNewBillClicked"
            @qrcodeClicked="onQrcodeClicked"
            @statsClicked="onStatsClicked"
            @settleClicked="onSettleClicked"
            @categoryClicked="onCategoryClicked"
            @currencyClicked="onCurrencyClicked"
            @detailClicked="onDetailClicked"
            @newMember="onNewMember"
            @memberEdited="onMemberEdited"
            @createProject="onCreateProject"
        />
        <AppContent>
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
                <Settlement
                    v-if="mode === 'settle'"
                    :projectId="currentProjectId"
                    @autoSettled="onAutoSettled"
                />
                <CategoryManagement
                    v-if="mode === 'category'"
                    :projectId="currentProjectId"
                    @categoryDeleted="onCategoryDeleted"
                />
                <CurrencyManagement
                    v-if="mode === 'currency'"
                    :projectId="currentProjectId"
                />
            </div>
        </AppContent>
        <Sidebar
            v-if="currentProjectId"
            :projectId="currentProjectId"
            :show="showSidebar"
            @close="showSidebar = false"
            />
        <!--router-view name="sidebar" /-->
        <img id="dummylogo"/>
    </Content>
</template>

<script>
import AppNavigation from './components/AppNavigation'
import BillForm from './BillForm';
import BillList from './BillList';
import CategoryManagement from './CategoryManagement';
import CurrencyManagement from './CurrencyManagement';
import MoneyBusterLink from './MoneyBusterLink';
import Statistics from './Statistics';
import Settlement from './Settlement';
import Sidebar from './components/Sidebar';
import cospend from './state';
import {generateUrl} from '@nextcloud/router';
import {getCurrentUser} from '@nextcloud/auth';
import * as Notification from './notification';
import * as constants from './constants';
import {rgbObjToHex, saveOptionValue, slugify} from './utils';
import { getMemberName } from './member';
import {
    Content, AppContent
} from '@nextcloud/vue'


export default {
    name: 'App',
    components: {
        AppNavigation,
        BillList,
        BillForm,
        MoneyBusterLink,
        Statistics,
        Settlement,
        CategoryManagement,
        CurrencyManagement,
        Sidebar,
        Content, AppContent
    },
    data() {
        return {
            mode: 'edition',
            cospend: cospend,
            projects: {},
            bills: {},
            billLists: {},
            members: {},
            billsLoading: false,
            currentBill: null,
            filterQuery: null,
            showSidebar: false
        }
    },
    computed: {
        currentProjectId() {
            return this.cospend.currentProjectId;
        },
        currentProject() {
            return this.projects[this.currentProjectId];
        },
        selectedBillId() {
            return (this.currentBill !== null) ? this.currentBill.id : -1;
        },
        currentBills() {
            return (this.currentProjectId && this.billLists.hasOwnProperty(this.currentProjectId)) ?
                (
                    this.filterQuery ?
                        this.getFilteredBills(this.billLists[this.currentProjectId])
                        : this.billLists[this.currentProjectId]
                )
                : [];
        },
        defaultPayerId() {
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
    provide() {
        return {
        }
    },
    created() {
        if (!cospend.pageIsPublic) {
            new OCA.Search(this.filter, this.cleanSearch);
        }
        this.getProjects();
    },
    mounted() {
        // once this is done, it becomes reactive...
        //this.$set(this.cospend, 'selectedBillId', -1);
    },
    methods: {
        onDetailClicked(projectid) {
            const sameProj = cospend.currentProjectId === projectid;
            if (cospend.currentProjectId !== projectid) {
                this.selectProject(projectid);
            }
            this.showSidebar = sameProj ? !this.showSidebar : true;
        },
        filter(qs) {
            this.filterQuery = qs;
        },
        cleanSearch() {
            this.filterQuery = null;
        },
        getFilteredBills(billList) {
            const filteredBills = []
            // Make sure to escape user input before creating regex from it:
            var regex = new RegExp(this.filterQuery.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, "\\$&"), "i");
            let bill;
            for (let i = 0; i < billList.length; i++) {
                bill = billList[i];
                if (regex.test(bill.what)) {
                    filteredBills.push(bill);
                }
            }
            return filteredBills;
        },
        cleanupBills() {
            const billList = this.billLists[cospend.currentProjectId];
            for (let i = 0; i < billList.length; i++) {
                if (billList[i].id === 0) {
                    billList.splice(i, 1);
                    break;
                }
            }
        },
        onBillCreated(bill, select) {
            this.bills[cospend.currentProjectId][bill.id] = bill;
            this.billLists[cospend.currentProjectId].push(bill);
            this.cleanupBills();
            if (select) {
                this.currentBill = bill;
            }
            this.updateBalances(cospend.currentProjectId);
        },
        onBillSaved(bill) {
            this.updateBalances(cospend.currentProjectId);
        },
        onBillDeleted(bill) {
            const billList = this.billLists[cospend.currentProjectId];
            billList.splice(billList.indexOf(bill), 1);
            if (bill.id === this.selectedBillId) {
                this.currentBill = null;
            }
            this.updateBalances(cospend.currentProjectId);
        },
        onProjectClicked(projectid) {
            if (cospend.currentProjectId !== projectid) {
                this.selectProject(projectid);
            }
        },
        onDeleteProject(projectid) {
            this.deleteProject(projectid);
        },
        onQrcodeClicked(projectid) {
            if (cospend.currentProjectId !== projectid) {
                this.selectProject(projectid);
            }
            this.currentBill = null;
            this.mode = 'qrcode';
        },
        onStatsClicked(projectid) {
            if (cospend.currentProjectId !== projectid) {
                this.selectProject(projectid);
            }
            this.currentBill = null;
            this.mode = 'stats';
        },
        onSettleClicked(projectid) {
            if (cospend.currentProjectId !== projectid) {
                this.selectProject(projectid);
            }
            this.currentBill = null;
            this.mode = 'settle';
        },
        onCategoryClicked(projectid) {
            if (cospend.currentProjectId !== projectid) {
                this.selectProject(projectid);
            }
            this.currentBill = null;
            this.mode = 'category';
        },
        onCurrencyClicked(projectid) {
            if (cospend.currentProjectId !== projectid) {
                this.selectProject(projectid);
            }
            this.currentBill = null;
            this.mode = 'currency';
        },
        onNewMember(projectid, name) {
            if (this.getMemberNames(projectid).includes(name)) {
                Notification.showTemporary(t('cospend', 'Member {name} already exists', {name: name}));
            } else {
                this.createMember(projectid, name);
            }
        },
        onMemberEdited(projectid, memberid) {
            this.editMember(projectid, memberid);
        },
        getMemberNames(projectid) {
            const res = [];
            for (const mid in this.members[projectid]) {
                res.push(this.members[projectid][mid].name);
            }
            return res;
        },
        selectProject(projectid, save=true) {
            this.mode = 'edition';
            this.currentBill = null;
            this.getBills(projectid);
            if (save) {
                saveOptionValue({selectedProject: projectid});
            }
            cospend.currentProjectId = projectid;
        },
        deselectProject() {
            this.mode = 'edition';
            this.currentBill = null;
            cospend.currentProjectId = null;
        },
        onAutoSettled(projectid) {
            this.getBills(projectid);
        },
        onNewBillClicked() {
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
        onBillClicked(billid) {
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
        getProjects() {
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
                if (!cospend.pageIsPublic) {
                    let proj;
                    for (let i = 0; i < response.length; i++) {
                        proj = response[i];
                        that.addProject(proj);
                    }
                    if (cospend.restoredCurrentProjectId !== null && cospend.restoredCurrentProjectId in that.projects) {
                        that.selectProject(cospend.restoredCurrentProjectId, false);
                    }
                } else {
                    if (!response.myaccesslevel) {
                        response.myaccesslevel = response.guestaccesslevel;
                    }
                    that.addProject(response);
                    that.selectProject(response.id, false);
                    //that.projects[response.id] = response;
                    //that.$set(cospend.projects, response.id, response);
                    //cospend.currentProjectId = cospend.projectid;
                }
            }).always(function() {
            }).fail(function(response) {
                Notification.showTemporary(t('cospend', 'Failed to get projects') +
                    ': ' + (response.responseJSON)
                );
            });
        },
        getBills(projectid) {
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
                that.updateBalances(projectid);
            }).always(function() {
                that.billsLoading = false;
            }).fail(function() {
                Notification.showTemporary(t('cospend', 'Failed to get bills'));
            });
        },
        addProject(proj) {
            cospend.projects[proj.id] = proj;
            this.$set(this.projects, proj.id, proj);

            cospend.members[proj.id] = {};
            this.$set(this.members, proj.id, cospend.members[proj.id]);
            for (let i = 0; i < proj.members.length; i++) {
                cospend.members[proj.id][proj.members[i].id] = proj.members[i];
                this.$set(this.members[proj.id], proj.members[i].id, proj.members[i]);
                //proj.members[i].balance = proj.balance[proj.members[i].id];
                this.$set(this.members[proj.id][proj.members[i].id], 'balance', proj.balance[proj.members[i].id]);
                //proj.members[i].color = rgbObjToHex(proj.members[i].color).replace('#', '');
                this.$set(this.members[proj.id][proj.members[i].id], 'color', rgbObjToHex(proj.members[i].color).replace('#', ''));
            }

            cospend.bills[proj.id] = {};
            this.$set(this.bills, proj.id, cospend.bills[proj.id]);

            cospend.billLists[proj.id] = [];
            this.$set(this.billLists, proj.id, cospend.billLists[proj.id]);
            //this.$set(cospend.projects, proj.id, proj);
        },
        onCreateProject(name) {
            if (!name) {
                Notification.showTemporary(t('cospend', 'Invalid project name'));
            } else {
                const id = slugify(name);
                this.createProject(name, id);
            }
        },
        createProject(name, id) {
            const that = this;
            const req = {
                id: id,
                name: name,
                password: null
            };
            const url = generateUrl('/apps/cospend/projects');
            $.ajax({
                type: 'POST',
                url: url,
                data: req,
                async: true,
            }).done(function(response) {
                that.addProject(response);
                that.selectProject(response.id);
            }).always(function() {
                $('#createproject').removeClass('icon-loading-small');
            }).fail(function(response) {
                Notification.showTemporary(t('cospend', 'Failed to create project') + ': ' + response.responseJSON.message);
            });
        },
        deleteProject(projectid) {
            const that = this;
            const req = {};
            let url;
            if (!cospend.pageIsPublic) {
                url = generateUrl('/apps/cospend/projects/' + projectid);
            } else {
                url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password);
            }
            $.ajax({
                type: 'DELETE',
                url: url,
                data: req,
                async: true,
            }).done(function() {
                that.currentBill = null;
                that.$delete(that.projects, projectid);
                that.$delete(that.bills, projectid);
                that.$delete(that.billLists, projectid);
                that.$delete(that.members, projectid);

                if (cospend.pageIsPublic) {
                    const redirectUrl = generateUrl('/apps/cospend/login');
                    window.location.replace(redirectUrl);
                }
                Notification.showTemporary(t('cospend', 'Deleted project {id}', {id: projectid}));
                that.deselectProject();
            }).always(function() {
            }).fail(function(response) {
                Notification.showTemporary(
                    t('cospend', 'Failed to delete project') +
                    ': ' + (response.responseJSON)
                );
            });
        },
        updateBalances(projectid) {
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
                    that.$set(that.members[projectid][memberid], 'balance', balance);
                }
            }).always(function() {
            }).fail(function() {
                Notification.showTemporary(t('cospend', 'Failed to update balances'));
            });
        },
        createMember(projectid, name) {
            const that = this;
            const req = {
                name: name
            };
            let url;
            if (!cospend.pageIsPublic) {
                url = generateUrl('/apps/cospend/projects/' + projectid + '/members');
            } else {
                url = generateUrl('/apps/cospend/apiv2/projects/' + cospend.projectid + '/' + cospend.password + '/members');
            }
            $.ajax({
                type: 'POST',
                url: url,
                data: req,
                async: true,
            }).done(function(response) {
                response.balance = 0;
                response.color = rgbObjToHex(response.color).replace('#', '');
                that.$set(that.members[projectid], response.id, response);
                that.projects[projectid].members.unshift(response);
                Notification.showTemporary(t('cospend', 'Created member {name}', {name: name}));
            }).always(function() {
            }).fail(function(response) {
                Notification.showTemporary(
                    t('cospend', 'Failed to add member') +
                    ': ' + (response.responseJSON.message)
                );
            });
        },
        editMember(projectid, memberid) {
            const that = this;
            const member = this.members[projectid][memberid];
            const req = {
                name: member.name,
                weight: member.weight,
                activated: member.activated,
                color: member.color,
                userid: member.userid
            };
            let url;
            if (!cospend.pageIsPublic) {
                url = generateUrl('/apps/cospend/projects/' + projectid + '/members/' + memberid);
            } else {
                url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/members/' + memberid);
            }
            $.ajax({
                type: 'PUT',
                url: url,
                data: req,
                async: true,
            }).done(function(response) {
                Notification.showTemporary(t('cospend', 'Member saved'));
                that.updateBalances(cospend.currentProjectId);
            }).always(function() {
            }).fail(function(response) {
                Notification.showTemporary(
                    t('cospend', 'Failed to save member') +
                    ': ' + (response.responseJSON.message)
                );
            });
        },
        onCategoryDeleted(catid) {
            let bill;
            for (const bid in this.bills[this.currentProjectId]) {
                bill = this.bills[this.currentProjectId][bid];
                if (bill.categoryid === catid) {
                    bill.categoryid = 0;
                }
            }
        },
    }
}
</script>

<style lang="scss" scoped>
    /*#content {
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
    }*/
</style>

<style>
    #content * {
        box-sizing: border-box;
    }
</style>