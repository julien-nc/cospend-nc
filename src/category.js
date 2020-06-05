/*jshint esversion: 6 */

import Vue from 'vue';
import './bootstrap';
import CategoryManagement from './CategoryManagement';
import {generateUrl} from '@nextcloud/router';
import {getProjectName, selectProject} from './project';
import * as Notification from './notification';
import * as constants from './constants';
import cospend from './state';
import {getBills} from './bill';
import EmojiButton from '@joeattardi/emoji-button';
import {Timer} from "./utils";

export function categoryEvents() {
    $('body').on('click', '.manageProjectCategories', function() {
        const projectid = $(this).parent().parent().parent().parent().attr('projectid');
        getProjectCategories(projectid);
    });

    //$('body').on('click', '.addCategoryOk', function() {
    //    const projectid = $('#catTitle').attr('projectid');
    //    const name = $('#addCategoryNameInput').val();
    //    if (name === null || name === '') {
    //        Notification.showTemporary(t('cospend', 'Category name should not be empty'));
    //        return;
    //    }
    //    const icon = $('#addCategoryIconInput').val();
    //    if (icon === null || icon === '') {
    //        Notification.showTemporary(t('cospend', 'Category icon should not be empty'));
    //        return;
    //    }
    //    const color = $('#addCategoryColorInput').val();
    //    if (color === null || color === '') {
    //        Notification.showTemporary(t('cospend', 'Category color should not be empty'));
    //        return;
    //    }
    //    addCategoryDb(projectid, name, icon, color);
    //});

    //$('body').on('keyup', '#addCategoryNameInput, #addCategoryIconInput', function(e) {
    //    if (e.key === 'Enter') {
    //        const projectid = $('#catTitle').attr('projectid');
    //        const name = $('#addCategoryNameInput').val();
    //        if (name === null || name === '') {
    //            Notification.showTemporary(t('cospend', 'Category name should not be empty'));
    //            return;
    //        }
    //        const icon = $('#addCategoryIconInput').val();
    //        if (icon === null || icon === '') {
    //            Notification.showTemporary(t('cospend', 'Category icon should not be empty'));
    //            return;
    //        }
    //        const color = $('#addCategoryColorInput').val();
    //        if (color === null || color === '') {
    //            Notification.showTemporary(t('cospend', 'Category color should not be empty'));
    //            return;
    //        }
    //        addCategoryDb(projectid, name, icon, color);
    //    }
    //});

    //$('body').on('click', '.deleteOneCategory', function() {
    //    const projectid = $('#catTitle').attr('projectid');
    //    const categoryId = $(this).parent().parent().attr('categoryid');
    //    if ($(this).hasClass('icon-history')) {
    //        $(this).removeClass('icon-history').addClass('icon-delete');
    //        cospend.categoryDeletionTimer[categoryId].pause();
    //        delete cospend.categoryDeletionTimer[categoryId];
    //    } else {
    //        $(this).addClass('icon-history').removeClass('icon-delete');
    //        cospend.categoryDeletionTimer[categoryId] = new Timer(function() {
    //            deleteCategoryDb(projectid, categoryId);
    //        }, 7000);
    //    }
    //});

    //$('body').on('click', '.editOneCategory', function() {
    //    $(this).parent().hide();
    //    $(this).parent().parent().find('.one-category-edit').show()
    //        .css('display', 'grid')
    //        .find('.editCategoryNameInput').focus().select();
    //});

    //$('body').on('click', '.editCategoryOk', function() {
    //    const projectid = $('#catTitle').attr('projectid');
    //    const categoryId = $(this).parent().parent().parent().attr('categoryid');
    //    const name = $(this).parent().parent().find('.editCategoryNameInput').val();
    //    if (name === null || name === '') {
    //        Notification.showTemporary(t('cospend', 'Category name should not be empty'));
    //        return;
    //    }
    //    const icon = $(this).parent().parent().find('.editCategoryIconInput').val();
    //    if (icon === null || icon === '') {
    //        Notification.showTemporary(t('cospend', 'Category icon should not be empty'));
    //        return;
    //    }
    //    const color = $(this).parent().parent().find('.editCategoryColorInput').val();
    //    if (color === null || color === '') {
    //        Notification.showTemporary(t('cospend', 'Category color should not be empty'));
    //        return;
    //    }
    //    editCategoryDb(projectid, categoryId, name, icon, color);
    //});

    //$('body').on('keyup', '.editCategoryNameInput, .editCategoryIconInput', function(e) {
    //    if (e.key === 'Enter') {
    //        const projectid = $('#catTitle').attr('projectid');
    //        const categoryId = $(this).parent().parent().attr('categoryid');
    //        const name = $(this).parent().find('.editCategoryNameInput').val();
    //        if (name === null || name === '') {
    //            Notification.showTemporary(t('cospend', 'Category name should not be empty'));
    //            return;
    //        }
    //        const icon = $(this).parent().find('.editCategoryIconInput').val();
    //        if (icon === null || icon === '') {
    //            Notification.showTemporary(t('cospend', 'Category icon should not be empty'));
    //            return;
    //        }
    //        const color = $(this).parent().find('.editCategoryColorInput').val();
    //        if (color === null || color === '') {
    //            Notification.showTemporary(t('cospend', 'Category color should not be empty'));
    //            return;
    //        }
    //        editCategoryDb(projectid, categoryId, name, icon, color);
    //    }
    //});
    //$('body').on('click', '.one-category-label-color', function(e) {
    //    e.preventDefault();
    //});

    //$('body').on('click', '.editCategoryClose', function() {
    //    $(this).parent().parent().hide();
    //    $(this).parent().parent().parent().find('.one-category-label').show();
    //});
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

    //// emoji management
    //const button = $('#add-icon-button')[0];
    //const picker = new EmojiButton({position: 'auto', zIndex: 9999999});
    //picker.on('emoji', emoji => {
    //    $('#addCategoryIconInput').val(emoji);
    //});
    //button.addEventListener('click', () => {
    //    picker.togglePicker(button);
    //});
}