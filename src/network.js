/*jshint esversion: 6 */

import cospend from './state';
import {generateUrl} from '@nextcloud/router';
import axios from '@nextcloud/axios';
import {
    showSuccess,
    showError,
} from '@nextcloud/dialogs'

export function getOptionValues(successCB) {
    const url = generateUrl('/apps/cospend/option-values');
    const req = {};
    axios.get(url, req)
        .then(function (response) {
            successCB(response.data);
        })
        .catch(function (error) {
            showError(
                t('cospend', 'Failed to restore options values.')
            );
        })
        .then(function () {
        });
}

export function saveOptionValue(optionValues) {
    if (!cospend.pageIsPublic) {
        const req = {
            options: optionValues
        };
        const url = generateUrl('/apps/cospend/option-value');
        axios.put(url, req)
            .then(function (response) {
            })
            .catch(function (error) {
                showError(t('cospend', 'Failed to save option values') +
                    ': ' + error.response.request.responseText
                );
            })
            .then(function () {
            });
    }
}

export function setAllowAnonymousCreation(val) {
    const url = generateUrl('/apps/cospend/allow-anonymous-creation');
    const req = {
        allow: val
    };
    axios.put(url, req)
        .then(function (response) {
            showSuccess(
                t('cospend', 'Cospend setting saved.')
            );
        })
        .catch(function (error) {
            showError(
                t('cospend', 'Failed to save Cospend setting.')
            );
        })
        .then(function () {
        });
}

export function exportProject(filename, projectid, projectName) {
    const req = {
        params: {
            name: filename
        }
    };
    const url = generateUrl('/apps/cospend/export-csv-project/' + projectid);

    axios.get(url, req)
        .then(function (response) {
            showSuccess(t('cospend', 'Project {name} exported in {path}', { name: projectName, path: response.data.path }));
        })
        .catch(function (error) {
            showError(
                t('cospend', 'Failed to export project') +
                ': ' + error.response.request.responseText
            );
        })
        .then(function () {
        });
}

export function getProjects(callback) {
    const req = {};
    let url;
    if (!cospend.pageIsPublic) {
        url = generateUrl('/apps/cospend/projects');
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password);
    }
    axios.get(url, req)
        .then(function (response) {
            callback(response.data)
        })
        .catch(function (error) {
            showError(t('cospend', 'Failed to get projects') +
                ': ' + error.response.request.responseText
            );
        })
        .then(function () {
        });
}

export function getBills(projectid, successCB, doneCB) {
    const req = {};
    let url;
    if (!cospend.pageIsPublic) {
        url = generateUrl('/apps/cospend/projects/' + projectid + '/bills');
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/bills');
    }
    axios.get(url, req)
        .then(function (response) {
            successCB(projectid, response.data);
        })
        .catch(function (error) {
            showError(t('cospend', 'Failed to get projects') +
                ': ' + error.response.request.responseText
            );
        })
        .then(function () {
            doneCB();
        });
}

export function createProject(name, id, successCB) {
    const req = {
        id: id,
        name: name,
        password: null
    };
    const url = generateUrl('/apps/cospend/projects');
    axios.post(url, req)
        .then(function (response) {
            successCB(response.data);
        })
        .catch(function (error) {
            showError(t('cospend', 'Failed to create project') +
                ': ' + error.response.request.responseText
            );
        })
        .then(function () {
        });
}

export function deleteProject(projectid, successCB) {
    let url;
    if (!cospend.pageIsPublic) {
        url = generateUrl('/apps/cospend/projects/' + projectid);
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password);
    }
    axios.delete(url)
        .then(function (response) {
            successCB(projectid, response.data);
        })
        .catch(function (error) {
            showError(t('cospend', 'Failed to delete project') +
                ': ' + error.response.request.responseText
            );
        })
        .then(function () {
        });
}

export function updateBalances(projectid, successCB) {
    const req = {};
    let url;
    if (!cospend.pageIsPublic) {
        url = generateUrl('/apps/cospend/projects/' + projectid);
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password);
    }
    axios.get(url, req)
        .then(function (response) {
            successCB(projectid, response.data);
        })
        .catch(function (error) {
            showError(t('cospend', 'Failed to update balances') +
                ': ' + error.response.request.responseText
            );
        })
        .then(function () {
        });
}

export function createMember(projectid, name, userid, successCB) {
    const req = {
        name: name
    };
    if (userid !== null) {
        req.userid = userid;
    }
    let url;
    if (!cospend.pageIsPublic) {
        url = generateUrl('/apps/cospend/projects/' + projectid + '/members');
    } else {
        url = generateUrl('/apps/cospend/apiv2/projects/' + cospend.projectid + '/' + cospend.password + '/members');
    }
    axios.post(url, req)
        .then(function (response) {
            successCB(projectid, name, response.data);
        })
        .catch(function (error) {
            showError(t('cospend', 'Failed to add member') +
                ': ' + error.response.request.responseText
            );
        })
        .then(function () {
        });
}

export function editMember(projectid, member, successCB) {
    const memberid = member.id;
    const req = {
        name: member.name,
        weight: member.weight,
        activated: member.activated,
        color: member.color,
        userid: (member.userid === null) ? '' : member.userid
    };
    let url;
    if (!cospend.pageIsPublic) {
        url = generateUrl('/apps/cospend/projects/' + projectid + '/members/' + memberid);
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/members/' + memberid);
    }
    axios.put(url, req)
        .then(function (response) {
            successCB();
        })
        .catch(function (error) {
            showError(t('cospend', 'Failed to save member') +
                ': ' + error.response.request.responseText
            );
        })
        .then(function () {
        });
}

export function editProject(project, password, successCB) {
    const projectid = project.id;
    const req = {
        name: project.name,
        contact_email: null,
        password: password,
        autoexport: project.autoexport,
        currencyname: project.currencyname
    };
    let url;
    if (!cospend.pageIsPublic) {
        url = generateUrl('/apps/cospend/projects/' + projectid);
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password);
    }
    axios.put(url, req)
        .then(function (response) {
            successCB(password);
        })
        .catch(function (error) {
            showError(t('cospend', 'Failed to edit project') +
                ': ' + error.response.request.responseText
            );
        })
        .then(function () {
        });
}

export function saveBill(projectid, bill, successCB, doneCB) {
    const req = {
        what: bill.what,
        comment: bill.comment,
        timestamp: bill.timestamp,
        payer: bill.payer_id,
        payed_for: bill.owerIds.join(','),
        amount: bill.amount,
        repeat: bill.repeat,
        repeatallactive: bill.repeatallactive ? 1 : 0,
        repeatuntil: bill.repeatuntil,
        paymentmode: bill.paymentmode,
        categoryid: bill.categoryid
    };
    let url;
    if (!cospend.pageIsPublic) {
        url = generateUrl('/apps/cospend/projects/' + projectid +'/bills/' + bill.id);
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/bills/' + bill.id);
    }
    axios.put(url, req)
        .then(function (response) {
            successCB();
        })
        .catch(function (error) {
            showError(t('cospend', 'Failed to save bill') +
                ': ' + error.response.request.responseText
            );
        })
        .then(function () {
            doneCB();
        });
}

export function createBill(projectid, mode, req, billToCreate, successCB, doneCB) {
    let url;
    if (!cospend.pageIsPublic) {
        url = generateUrl('/apps/cospend/projects/' + projectid + '/bills');
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/bills');
    }
    axios.post(url, req)
        .then(function (response) {
            successCB(response.data, billToCreate, mode);
        })
        .catch(function (error) {
            showError(t('cospend', 'Failed to create bill') +
                ': ' + error.response.request.responseText
            );
        })
        .then(function () {
            doneCB();
        });
}

export function generatePublicLinkToFile(targetPath, successCB) {
    const req = {
        path: targetPath
    };
    const url = generateUrl('/apps/cospend/getPublicFileShare');
    axios.post(url, req)
        .then(function (response) {
            successCB(response.data);
        })
        .catch(function (error) {
            showError(t('cospend', 'Failed to generate public link to file') +
                ': ' + error.response.request.responseText
            );
        })
        .then(function () {
        });
}

export function deleteBill(projectid, bill, successCB) {
    const req = {};
    let url;
    if (!cospend.pageIsPublic) {
        url = generateUrl('/apps/cospend/projects/' + projectid + '/bills/' + bill.id);
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/bills/' + bill.id);
    }
    axios.delete(url)
        .then(function (response) {
            successCB(bill);
        })
        .catch(function (error) {
            showError(t('cospend', 'Failed to delete bill') +
                ': ' + error.response.request.responseText
            );
        })
        .then(function () {
        });
}

export function checkPassword(projectid, password, successCB) {
    const url = generateUrl('/apps/cospend/checkpassword/' + projectid + '/' + password);
    axios.get(url)
        .then(function (response) {
            successCB(response.data);
        })
        .catch(function (error) {
            showError(
                t('cospend', 'Failed to check password.')
            );
        })
        .then(function () {
        });
}

export function importProject(targetPath, isSplitWise, successCB) {
    const req = {
        params: {
            path: targetPath
        }
    };
    let url;
    if (isSplitWise) {
        url = generateUrl('/apps/cospend/import-sw-project');
    } else {
        url = generateUrl('/apps/cospend/import-csv-project');
    }
    axios.get(url, req)
        .then(function (response) {
            successCB(response.data);
        })
        .catch(function (error) {
            showError(t('cospend', 'Failed to import project file') +
                ': ' + error.response.request.responseText
            );
        })
        .then(function () {
        });
}

export function addCategory(projectid, name, icon, color, successCB) {
    const req = {
        name: name,
        icon: icon,
        color: color
    };
    let url;
    if (!cospend.pageIsPublic) {
        url = generateUrl('/apps/cospend/projects/' + projectid + '/category');
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/category');
    }
    axios.post(url, req)
        .then(function (response) {
            successCB(response.data, name, icon, color);
        })
        .catch(function (error) {
            showError(t('cospend', 'Failed to add category') +
                ': ' + error.response.request.responseText
            );
        })
        .then(function () {
        });
}
 export function deleteCategory(projectid, categoryid, successCB) {
    let url;
    if (!cospend.pageIsPublic) {
        url = generateUrl('/apps/cospend/projects/' + projectid + '/category/' + categoryid);
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/category/' + categoryid);
    }
    axios.delete(url)
        .then(function (response) {
            successCB(categoryid);
        })
        .catch(function (error) {
            showError(t('cospend', 'Failed to delete category') +
                ': ' + error.response.request.responseText
            );
        })
        .then(function () {
        });
 }

export function editCategory(projectid, category, backupCategory, failCB) {
    const req = {
        name: category.name,
        icon: category.icon,
        color:category.color
    };
    let url;
    if (!cospend.pageIsPublic) {
        url = generateUrl('/apps/cospend/projects/' + projectid + '/category/' + category.id);
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/category/' + category.id);
    }
    axios.put(url, req)
        .then(function (response) {
        })
        .catch(function (error) {
            failCB(category, backupCategory);
            showError(t('cospend', 'Failed to edit category') +
                ': ' + error.response.request.responseText
            );
        })
        .then(function () {
        });
}

export function addCurrency(projectid, name, rate, successCB) {
    const req = {
        name: name,
        rate: rate
    };
    let url;
    if (!cospend.pageIsPublic) {
        req.projectid = projectid;
        url = generateUrl('/apps/cospend/projects/' + projectid + '/currency');
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/currency');
    }
    axios.post(url, req)
        .then(function (response) {
            successCB(response.data, name, rate);
        })
        .catch(function (error) {
            showError(t('cospend', 'Failed to add currency') +
                ': ' + error.response.request.responseText
            );
        })
        .then(function () {
        });
}

export function deleteCurrency(projectid, currency, successCB) {
    let url;
    if (!cospend.pageIsPublic) {
        url = generateUrl('/apps/cospend/projects/' + projectid + '/currency/' + currency.id);
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/currency/' + currency.id);
    }
    axios.delete(url)
        .then(function (response) {
            successCB(currency);
        })
        .catch(function (error) {
            showError(t('cospend', 'Failed to delete currency') +
                ': ' + error.response.request.responseText
            );
        })
        .then(function () {
        });
}

export function editCurrency(projectid, currency, backupCurrency, failCB) {
    const req = {
        name: currency.name,
        rate: currency.exchange_rate
    };
    let url;
    if (!cospend.pageIsPublic) {
        url = generateUrl('/apps/cospend/projects/' + projectid + '/currency/' + currency.id);
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/currency/' + currency.id);
    }
    axios.put(url, req)
        .then(function (response) {
        })
        .catch(function (error) {
            failCB(currency, backupCurrency);
            showError(t('cospend', 'Failed to edit currency') +
                ': ' + error.response.request.responseText
            );
        })
        .then(function () {
        });
}

export function getStats(projectid, params, isFiltered, successCB) {
    const req = {
        params: params
    }
    let url;
    if (!cospend.pageIsPublic) {
        url = generateUrl('/apps/cospend/projects/' + projectid + '/statistics');
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/statistics');
    }
    axios.get(url, req)
        .then(function (response) {
            successCB(response.data, isFiltered);
        })
        .catch(function (error) {
            showError(
                t('cospend', 'Failed to get statistics.')
            );
        })
        .then(function () {
        });
}

export function exportStats(projectid, params, doneCB) {
    const req = {
        params: params
    }
    const url = generateUrl('/apps/cospend/export-csv-statistics/'+ projectid);
    axios.get(url, req)
        .then(function (response) {
            showSuccess(t('cospend', 'Project statistics exported in {path}', {path: response.data.path}));
        })
        .catch(function (error) {
            showError(t('cospend', 'Failed to export project statistics') +
                ': ' + error.response.request.responseText
            );
        })
        .then(function () {
            doneCB();
        });
}

export function getSettlement(projectid, centeredOn, successCB, failCB) {
    if (parseInt(centeredOn) === 0) {
        centeredOn = null;
    }
    const req = {
        params: {
            centeredOn: centeredOn
        }
    };
    let url;
    if (!cospend.pageIsPublic) {
        url = generateUrl('/apps/cospend/projects/' + projectid + '/settlement');
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/settle');
    }
    axios.get(url, req)
        .then(function (response) {
            successCB(response.data);
        })
        .catch(function (error) {
            failCB();
            showError(t('cospend', 'Failed to get settlement') +
                ': ' + error.response.request.responseText
            );
        })
        .then(function () {
        });
}

export function autoSettlement(projectid, centeredOn, successCB) {
    const req = {
        params: {
            centeredOn: (parseInt(centeredOn) === 0) ? null : centeredOn
        }
    };
    let url;
    if (!cospend.pageIsPublic) {
        url = generateUrl('/apps/cospend/projects/'+ projectid +'/auto-settlement');
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/autosettlement');
    }
    axios.get(url, req)
        .then(function (response) {
            successCB();
        })
        .catch(function (error) {
            showError(t('cospend', 'Failed to add project settlement bills') +
                ': ' + error.response.request.responseText
            );
        })
        .then(function () {
        });
}

export function exportSettlement(projectid, centeredOn, successCB) {
    const req = {
        params: {
            centeredOn: (parseInt(centeredOn) === 0) ? null : centeredOn
        }
    };
    const url = generateUrl('/apps/cospend/export-csv-settlement/' + projectid);
    axios.get(url, req)
        .then(function (response) {
            successCB(response.data);
        })
        .catch(function (error) {
            showError(t('cospend', 'Failed to export project settlement') +
                ': ' + error.response.request.responseText
            );
        })
        .then(function () {
        });
}

export function loadUsers(successCB) {
    const url = generateUrl('/apps/cospend/user-list');
    axios.get(url)
        .then(function (response) {
            successCB(response.data);
        })
        .catch(function (error) {
            showError(t('cospend', 'Failed to get user list') +
                ': ' + error.response.request.responseText
            );
        })
        .then(function () {
        });
}

export function addSharedAccess(projectid, sh, successCB) {
    const req = {};
    let url;
    if (sh.type === 'u') {
        req.userid = sh.user;
        url = generateUrl('/apps/cospend/projects/' + projectid + '/user-share');
    } else if (sh.type === 'g') {
        req.groupid = sh.user;
        url = generateUrl('/apps/cospend/projects/' + projectid + '/group-share');
    } else if (sh.type === 'c') {
        req.circleid = sh.user;
        url = generateUrl('/apps/cospend/projects/' + projectid + '/circle-share');
    } else if (sh.type === 'l') {
        url = generateUrl('/apps/cospend/projects/' + projectid + '/public-share');
    }
    axios.post(url, req)
        .then(function (response) {
            successCB(response.data, sh);
        })
        .catch(function (error) {
            showError(t('cospend', 'Failed to add shared access') +
                ': ' + error.response.request.responseText
            );
        })
        .then(function () {
        });
}

export function setAccessLevel(projectid, access, level, successCB) {
    const req = {
        accesslevel: level
    };
    const url = generateUrl('/apps/cospend/projects/' + projectid + '/share-access-level/' + access.id);
    axios.put(url, req)
        .then(function (response) {
            successCB(access, level);
        })
        .catch(function (error) {
            showError(t('cospend', 'Failed to edit shared access level') +
                ': ' + error.response.request.responseText
            );
        })
        .then(function () {
        });
}

export function deleteAccess(projectid, access, successCB) {
    const shid = access.id;
    const req = {};
    let url;
    if (access.type === 'u') {
        url = generateUrl('/apps/cospend/projects/' + projectid + '/user-share/' + shid);
    } else if (access.type === 'g') {
        url = generateUrl('/apps/cospend/projects/' + projectid + '/group-share/' + shid);
    } else if (access.type === 'c') {
        url = generateUrl('/apps/cospend/projects/' + projectid + '/circle-share/' + shid);
    } else if (access.type === 'l') {
        url = generateUrl('/apps/cospend/projects/' + projectid + '/public-share/' + shid);
    }
    axios.delete(url)
        .then(function (response) {
            successCB(access);
        })
        .catch(function (error) {
            showError(t('cospend', 'Failed to delete shared access') +
                ': ' + error.response.request.responseText
            );
        })
        .then(function () {
        });
}

export function setGuestAccessLevel(projectid, level, successCB) {
    const req = {
        accesslevel: level
    };
    let url;
    if (!cospend.pageIsPublic) {
        url = generateUrl('/apps/cospend/projects/' + projectid + '/guest-access-level');
    } else {
        url = generateUrl('/apps/cospend/api/projects/' + cospend.projectid + '/' + cospend.password + '/guest-access-level');
    }
    axios.put(url, req)
        .then(function (response) {
            successCB(level);
        })
        .catch(function (error) {
            showError(t('cospend', 'Failed to edit guest access level') +
                ': ' + error.response.request.responseText
            );
        })
        .then(function () {
        });
}