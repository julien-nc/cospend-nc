/*jshint esversion: 6 */

import Vue from 'vue';
import './bootstrap';
import CategoryManagement from './CategoryManagement';
import {generateUrl} from '@nextcloud/router';
import {getProjectName, selectProject} from './project';
import * as Notification from './notification';
import cospend from './state';

export function categoryEvents() {
    $('body').on('click', '.manageProjectCategories', function() {
        const projectid = $(this).parent().parent().parent().parent().attr('projectid');
        getProjectCategories(projectid);
    });
}

export function getProjectCategories(projectid) {
    $('#billdetail').html('<h2 class="icon-loading-small"></h2>');
    const req = {};
    let url, type;
    if (!cospend.pageIsPublic) {
        req.projectid = projectid;
        url = generateUrl('/apps/cospend/getProjectInfo');
        type = 'POST';
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password);
        type = 'GET';
    }
    cospend.currentGetProjectsAjax = $.ajax({
        type: type,
        url: url,
        data: req,
        async: true,
    }).done(function(response) {
        if (cospend.currentProjectId !== projectid) {
            selectProject($('.projectitem[projectid="' + projectid + '"]'));
        }
        displayCategories(projectid, response);
    }).always(function() {
    }).fail(function() {
        Notification.showTemporary(t('cospend', 'Failed to get project categories'));
        $('#billdetail').html('');
    });
}

export function displayCategories(projectid, projectInfo) {
    // deselect bill
    $('.billitem').removeClass('selectedbill');
    const categories = projectInfo.categories;
    const projectName = getProjectName(projectid);
    $('#billdetail').html('');
    $('.app-content-list').addClass('showdetails');
    const titleStr = t('cospend', 'Categories of project {name}', {name: projectName});

    $('#billdetail').html('');
    $('#billdetail').append($('<div/>', {id: 'app-details-toggle', tabindex: 0, class: 'icon-confirm'}))
        .append(
            $('<h2/>', {id: 'catTitle', projectid: projectid})
                .append($('<span/>', {class: 'icon-category-app-bundles'}))
                .append(titleStr)
        )
        .append($('<div/>', {id: 'manage-categories'}));

    new Vue({
        el: "#manage-categories",
        render: h => h(CategoryManagement),
    });
}