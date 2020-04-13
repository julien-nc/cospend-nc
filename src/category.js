/*jshint esversion: 6 */

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

    $('body').on('click', '.addCategoryOk', function() {
        const projectid = $('#catTitle').attr('projectid');
        const name = $('#addCategoryNameInput').val();
        if (name === null || name === '') {
            Notification.showTemporary(t('cospend', 'Category name should not be empty'));
            return;
        }
        const icon = $('#addCategoryIconInput').val();
        if (icon === null || icon === '') {
            Notification.showTemporary(t('cospend', 'Category icon should not be empty'));
            return;
        }
        const color = $('#addCategoryColorInput').val();
        if (color === null || color === '') {
            Notification.showTemporary(t('cospend', 'Category color should not be empty'));
            return;
        }
        addCategoryDb(projectid, name, icon, color);
    });

    $('body').on('keyup', '#addCategoryNameInput, #addCategoryIconInput', function(e) {
        if (e.key === 'Enter') {
            const projectid = $('#catTitle').attr('projectid');
            const name = $('#addCategoryNameInput').val();
            if (name === null || name === '') {
                Notification.showTemporary(t('cospend', 'Category name should not be empty'));
                return;
            }
            const icon = $('#addCategoryIconInput').val();
            if (icon === null || icon === '') {
                Notification.showTemporary(t('cospend', 'Category icon should not be empty'));
                return;
            }
            const color = $('#addCategoryColorInput').val();
            if (color === null || color === '') {
                Notification.showTemporary(t('cospend', 'Category color should not be empty'));
                return;
            }
            addCategoryDb(projectid, name, icon, color);
        }
    });

    $('body').on('click', '.deleteOneCategory', function() {
        const projectid = $('#catTitle').attr('projectid');
        const categoryId = $(this).parent().parent().attr('categoryid');
        if ($(this).hasClass('icon-history')) {
            $(this).removeClass('icon-history').addClass('icon-delete');
            cospend.categoryDeletionTimer[categoryId].pause();
            delete cospend.categoryDeletionTimer[categoryId];
        } else {
            $(this).addClass('icon-history').removeClass('icon-delete');
            cospend.categoryDeletionTimer[categoryId] = new Timer(function() {
                deleteCategoryDb(projectid, categoryId);
            }, 7000);
        }
    });

    $('body').on('click', '.editOneCategory', function() {
        $(this).parent().hide();
        $(this).parent().parent().find('.one-category-edit').show()
            .css('display', 'grid')
            .find('.editCategoryNameInput').focus().select();
    });

    $('body').on('click', '.editCategoryOk', function() {
        const projectid = $('#catTitle').attr('projectid');
        const categoryId = $(this).parent().parent().parent().attr('categoryid');
        const name = $(this).parent().parent().find('.editCategoryNameInput').val();
        if (name === null || name === '') {
            Notification.showTemporary(t('cospend', 'Category name should not be empty'));
            return;
        }
        const icon = $(this).parent().parent().find('.editCategoryIconInput').val();
        if (icon === null || icon === '') {
            Notification.showTemporary(t('cospend', 'Category icon should not be empty'));
            return;
        }
        const color = $(this).parent().parent().find('.editCategoryColorInput').val();
        if (color === null || color === '') {
            Notification.showTemporary(t('cospend', 'Category color should not be empty'));
            return;
        }
        editCategoryDb(projectid, categoryId, name, icon, color);
    });

    $('body').on('keyup', '.editCategoryNameInput, .editCategoryIconInput', function(e) {
        if (e.key === 'Enter') {
            const projectid = $('#catTitle').attr('projectid');
            const categoryId = $(this).parent().parent().attr('categoryid');
            const name = $(this).parent().find('.editCategoryNameInput').val();
            if (name === null || name === '') {
                Notification.showTemporary(t('cospend', 'Category name should not be empty'));
                return;
            }
            const icon = $(this).parent().find('.editCategoryIconInput').val();
            if (icon === null || icon === '') {
                Notification.showTemporary(t('cospend', 'Category icon should not be empty'));
                return;
            }
            const color = $(this).parent().find('.editCategoryColorInput').val();
            if (color === null || color === '') {
                Notification.showTemporary(t('cospend', 'Category color should not be empty'));
                return;
            }
            editCategoryDb(projectid, categoryId, name, icon, color);
        }
    });
    $('body').on('click', '.one-category-label-color', function(e) {
        e.preventDefault();
    });

    $('body').on('click', '.editCategoryClose', function() {
        $(this).parent().parent().hide();
        $(this).parent().parent().parent().find('.one-category-label').show();
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
        .append(
            $('<div/>', {id: 'manage-categories'})
                .append(
                    $('<div/>', {id: 'categories-div'})
                        .append(
                            $('<div/>', {id: 'add-category-div'})
                                .append(
                                    $('<label/>')
                                        .append($('<a/>', {class: 'icon icon-add'}))
                                        .append(t('cospend', 'Add category'))
                                )
                                .append(
                                    $('<div/>', {id: 'add-category'})
                                        .append($('<label/>', {for: 'addCategoryIconInput'}).text(t('cospend', 'Icon')))
                                        .append(
                                            $('<div/>', {id: 'add-icon-input-div'})
                                                .append($('<input/>', {type: 'text', value: '', maxlength: 3, id: 'addCategoryIconInput'}))
                                                .append($('<button/>', {id: 'add-icon-button'}).text('🙂'))
                                        )
                                        .append($('<label/>', {for: 'addCategoryNameInput'}).text(t('cospend', 'Name')))
                                        .append($('<input/>', {type: 'text', value: '', maxlength: 300, id: 'addCategoryNameInput'}))
                                        .append($('<label/>', {for: 'addCategoryColorInput'}).text(t('cospend', 'Color')))
                                        .append($('<input/>', {type: 'color', value: '', maxlength: 300, id: 'addCategoryColorInput'}))
                                        .append(
                                            $('<button/>', {class: 'addCategoryOk'})
                                                .append($('<span/>', {class: 'icon-add'}))
                                                .append($('<span/>').text(t('cospend', 'Add this category')))
                                        )
                                )
                                .append($('<hr/>'))
                        )
                        .append($('<br/>'))
                        .append(
                            $('<label/>')
                                .append($('<a/>', {class: 'icon icon-category-app-bundles'}))
                                .append(t('cospend', 'Category list'))
                        )
                        .append($('<br/>'))
                        .append($('<br/>'))
                        .append($('<div/>', {id: 'category-list'}))
                )
        );

    for (const catId in categories) {
        addCategory(projectid, catId, categories[catId]);
    }
    if (cospend.projects[projectid].myaccesslevel < constants.ACCESS.MAINTENER) {
        $('#add-category-div').hide();
        $('.editOneCategory').hide();
        $('.deleteOneCategory').hide();
    }
    // emoji management
    const button = $('#add-icon-button')[0];
    const picker = new EmojiButton({position: 'auto', zIndex: 9999999});
    picker.on('emoji', emoji => {
        $('#addCategoryIconInput').val(emoji);
    });
    button.addEventListener('click', () => {
        picker.togglePicker(button);
    });
}

export function addCategoryDb(projectid, name, icon, color) {
    $('.addCategoryOk').addClass('icon-loading-small');
    const req = {
        name: name,
        icon: icon,
        color: color
    };
    let url;
    if (!cospend.pageIsPublic) {
        req.projectid = projectid;
        url = generateUrl('/apps/cospend/addCategory');
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/category');
    }
    $.ajax({
        type: 'POST',
        url: url,
        data: req,
        async: true
    }).done(function(response) {
        cospend.projects[projectid].categories[response] = {
            name: name,
            icon: icon,
            color: color
        };
        addCategory(projectid, response, cospend.projects[projectid].categories[response]);
        Notification.showTemporary(t('cospend', 'Category {n} added', {n: name}));
    }).always(function() {
        $('.addCategoryOk').removeClass('icon-loading-small');
    }).fail(function(response) {
        Notification.showTemporary(
            t('cospend', 'Failed to add category') +
            ': ' + (response.responseJSON.message || response.responseText)
        );
    });
}

export function addCategory(projectid, catId, category) {
    $('#category-list').append(
        $('<div/>', {class: 'one-category', projectid: projectid, categoryid: catId})
            .append(
                $('<div/>', {class: 'one-category-label'})
                    .append($('<label/>', {class: 'one-category-label-icon'}).text((category.icon || '')))
                    .append($('<label/>', {class: 'one-category-label-label'}).text(category.name))
                    .append($('<input/>', {class: 'one-category-label-color', type: 'color', value: category.color, readonly: true}))
                    .append($('<input/>', {type: 'submit', value: '', class: 'icon-rename editOneCategory'}))
                    .append($('<input/>', {type: 'submit', value: '', class: 'icon-delete deleteOneCategory'}))
            )
            .append(
                $('<div/>', {class: 'one-category-edit'})
                    .append($('<label/>').text(t('cospend', 'Icon')))
                    .append(
                        $('<div/>', {class: 'edit-icon-input-div'})
                            .append($('<input/>', {type: 'text', value: (category.icon || ''), maxlength: 3, class: 'editCategoryIconInput', readonly: true}))
                            .append($('<button/>', {class: 'edit-icon-button'}).text('🙂'))
                    )
                    .append($('<label/>').text(t('cospend', 'Name')))
                    .append($('<input/>', {type: 'text', value: category.name, maxlength: 300, class: 'editCategoryNameInput'}))
                    .append($('<label/>').text(t('cospend', 'Color')))
                    .append($('<input/>', {type: 'color', value: category.color, class: 'editCategoryColorInput'}))
                    .append(
                        $('<div/>')
                            .append(
                                $('<button/>', {class: 'editCategoryClose'})
                                    .append($('<span/>', {class: 'icon-close'}))
                                    .append($('<span/>').text(t('cospend', 'Cancel')))
                            )
                            .append(
                                $('<button/>', {class: 'editCategoryOk'})
                                    .append($('<span/>', {class: 'icon-checkmark'}))
                                    .append($('<span/>').text(t('cospend', 'Save')))
                            )
                    )
            )
    );
    // emoji management
    const button = $('.one-category[categoryid='+catId+'] .edit-icon-button')[0];
    const picker = new EmojiButton({position: 'auto', zIndex: 9999999});
    picker.on('emoji', emoji => {
        $('.one-category[categoryid='+catId+'] .editCategoryIconInput').val(emoji);
    });
    button.addEventListener('click', () => {
        picker.togglePicker(button);
    });
}

export function deleteCategoryDb(projectid, categoryId) {
    $('.one-category[categoryid=' + categoryId + '] .deleteOneCategory').addClass('icon-loading-small');
    const req = {};
    let url, type;
    if (!cospend.pageIsPublic) {
        req.projectid = projectid;
        req.categoryid = categoryId;
        url = generateUrl('/apps/cospend/deleteCategory');
        type = 'POST';
    } else {
        type = 'DELETE';
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/category/' + categoryId);
    }
    $.ajax({
        type: type,
        url: url,
        data: req,
        async: true
    }).done(function() {
        $('.one-category[categoryid=' + categoryId + ']').fadeOut('normal', function() {
            $(this).remove();
        });
        delete cospend.projects[projectid].categories[categoryId];
        // reload bill list
        getBills(projectid);
    }).always(function() {
        $('.one-category[categoryid=' + categoryId + '] .deleteOneCategory').removeClass('icon-loading-small');
    }).fail(function(response) {
        Notification.showTemporary(
            t('cospend', 'Failed to delete category') +
            ': ' + response.responseJSON.message);
    });
}

export function editCategoryDb(projectid, categoryId, name, icon, color) {
    $('.one-category[categoryid=' + categoryId + '] .editCategoryOk').addClass('icon-loading-small');
    const req = {
        name: name,
        icon: icon,
        color: color
    };
    let url, type;
    if (!cospend.pageIsPublic) {
        req.projectid = projectid;
        req.categoryid = categoryId;
        url = generateUrl('/apps/cospend/editCategory');
        type = 'POST';
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/category/' + categoryId);
        type = 'PUT';
    }
    $.ajax({
        type: type,
        url: url,
        data: req,
        async: true
    }).done(function() {
        $('.one-category[categoryid=' + categoryId + '] .one-category-edit').hide();
        $('.one-category[categoryid=' + categoryId + '] .one-category-label').show()
            .find('.one-category-label-label').text(name);
        $('.one-category[categoryid=' + categoryId + '] .one-category-label .one-category-label-icon').text(icon || '');
        $('.one-category[categoryid=' + categoryId + '] .one-category-label .one-category-label-color').val(color);
        cospend.projects[projectid].categories[categoryId].name = name;
        cospend.projects[projectid].categories[categoryId].icon = icon;
        cospend.projects[projectid].categories[categoryId].color = color;
        // reload bill list
        getBills(projectid);
    }).always(function() {
        $('.one-category[categoryid=' + categoryId + '] .editCategoryOk').removeClass('icon-loading-small');
    }).fail(function(response) {
        Notification.showTemporary(
            t('cospend', 'Failed to edit category') +
            ': ' + response.responseJSON.message
        );
    });
}