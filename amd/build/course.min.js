/**
 * Course page Learning Prism controls (AMD format — no ES6 imports).
 *
 * Must be valid AMD with define() so Moodle can load it when cachejs/jsrev
 * serves amd/src directly. ES6 import/export breaks RequireJS and leaves
 * M.util.js_pending hanging, which disables Snap/Boost navigation (href="#").
 *
 * @module     local_learningprism/course
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'jquery',
    'core/ajax',
    'core/notification',
    'core/templates',
    'core/str'
], function($, Ajax, Notification, Templates, Str) {
    var pageCourseId = 0;
    var stateTitles = {
        request: '',
        processing: '',
        download: '',
        error: ''
    };

    var stateTitle = function(state) {
        return stateTitles[state] || stateTitles.request;
    };

    var selectedFormats = function(root) {
        return $(root).find('input[name="lpformat"]:checked').map(function() {
            return $(this).val();
        }).get();
    };

    var updateIconState = function(cmid, state) {
        var btn = document.querySelector('[data-lp-cmid="' + cmid + '"]');
        if (!btn) {
            return;
        }
        btn.setAttribute('data-lp-state', state);
        btn.classList.remove('lp-state-request', 'lp-state-processing', 'lp-state-download', 'lp-state-error');
        btn.classList.add('lp-state-' + state);
        btn.setAttribute('title', stateTitle(state));
        btn.setAttribute('aria-label', stateTitle(state));
    };

    /**
     * Find a node to append the control icon to (Boost + Snap, labels + linked activities).
     *
     * @param {Object} instance
     * @return {Element|null}
     */
    var findActivityNode = function(instance) {
        var cmid = String(instance.cmid);
        var activity = document.querySelector('#module-' + cmid) ||
            document.querySelector('li.activity[data-id="' + cmid + '"]') ||
            document.querySelector('.activity[data-cmid="' + cmid + '"]') ||
            document.querySelector('[data-for="cmitem"][data-id="' + cmid + '"]');

        if (!activity && instance.url) {
            var link = document.querySelector('a[href="' + instance.url + '"]') ||
                document.querySelector('a[href*="/mod/' + instance.modname + '/view.php?id=' + cmid + '"]') ||
                document.querySelector('.snap-asset-link a[href*="id=' + cmid + '"]');
            if (link) {
                activity = link.closest('li.activity, .activity, .snap-asset, [data-for="cmitem"]') || link.parentElement;
            }
        }

        if (!activity) {
            return null;
        }

        // Prefer visible title / content regions used by Boost 4.x and Snap.
        var candidates = [
            '.activity-grid .activity-name',
            '.activityname',
            '.activitytitle',
            '.activity-instance',
            '.contentwithoutlink',
            '.activity-item .description',
            '.activity-item',
            '.snap-asset-link',
            '.snap-asset-content',
            '.snap-asset'
        ];
        var i;
        for (i = 0; i < candidates.length; i++) {
            var node = activity.querySelector(candidates[i]);
            if (node) {
                return node;
            }
        }
        return activity;
    };

    var openModal = function(instance, canManage) {
        Ajax.call([{
            methodname: 'local_learningprism_get_activity_formats',
            args: {cmid: instance.cmid}
        }])[0].then(function(data) {
            var formats = (data.formats || []).map(function(f) {
                var isqueued = f.status === 'queued' || f.status === 'processing';
                var isready = f.status === 'ready';
                var pstatus = f.personalstatus || 'none';
                return Object.assign({}, f, {
                    isready: isready,
                    isnone: f.status === 'none',
                    isqueued: isqueued,
                    iserror: f.status === 'error',
                    lockcheckbox: isqueued,
                    ispersonalready: pstatus === 'ready',
                    ispersonalnone: pstatus === 'none',
                    ispersonalerror: pstatus === 'error',
                    supportspersonal: !!f.supportspersonal
                });
            });
            var hasRequestable = formats.some(function(f) {
                return f.isnone || f.iserror || (canManage && f.isready);
            });
            var showpersonal = formats.some(function(f) {
                return f.supportspersonal;
            });
            return Str.get_strings([
                {key: canManage ? 'modal:requestorregenerate' : 'modal:request', component: 'local_learningprism'},
                {key: 'modal:noneselected', component: 'local_learningprism'}
            ]).then(function(strings) {
            var requestLabel = strings[0];
            var noneSelectedMsg = strings[1];
            return Templates.render('local_learningprism/modal_body', {
                name: data.name,
                state: data.state,
                formats: formats,
                backgrounds: data.backgrounds || [],
                fonts: data.fonts || [],
                canmanage: canManage,
                cmid: instance.cmid,
                requestlabel: requestLabel,
                showrequest: hasRequestable,
                showdownload: data.state === 'download',
                showpersonal: showpersonal
            }).then(function(html) {
                var $existing = $('#local-learningprism-modal');
                if ($existing.length) {
                    $existing.remove();
                }
                var $modal = $('<div id="local-learningprism-modal" class="modal fade" tabindex="-1" role="dialog">' +
                    '<div class="modal-dialog modal-lg" role="document"><div class="modal-content">' +
                    '<div class="modal-header"><h5 class="modal-title"></h5>' +
                    '<button type="button" class="close" data-dismiss="modal" aria-label="Close">' +
                    '<span aria-hidden="true">&times;</span></button></div>' +
                    '<div class="modal-body"></div></div></div></div>');
                Str.get_string('modal:title', 'local_learningprism').then(function(title) {
                    $modal.find('.modal-title').text(title);
                    $modal.find('.modal-body').html(html);
                    $('body').append($modal);
                    if (typeof $modal.modal === 'function') {
                        $modal.modal('show');
                    } else {
                        $modal.addClass('show').css('display', 'block');
                    }

                    $modal.on('click', '[data-action="lp-request"]', function(e) {
                        e.preventDefault();
                        var formatsSel = selectedFormats($modal[0]);
                        if (!formatsSel.length) {
                            Notification.addNotification({message: noneSelectedMsg, type: 'error'});
                            return;
                        }
                        Ajax.call([{
                            methodname: 'local_learningprism_request_formats',
                            args: {cmid: instance.cmid, formats: formatsSel, personal: false}
                        }])[0].then(function(result) {
                            updateIconState(instance.cmid, result.state);
                            if (typeof $modal.modal === 'function') {
                                $modal.modal('hide');
                            } else {
                                $modal.remove();
                            }
                            return Str.get_string('modal:statusqueued', 'local_learningprism');
                        }).then(function(msg) {
                            Notification.addNotification({message: msg, type: 'info'});
                        }).catch(Notification.exception);
                    });

                    $modal.on('click', '[data-action="lp-request-personal"]', function(e) {
                        e.preventDefault();
                        var formatsSel = selectedFormats($modal[0]);
                        if (!formatsSel.length) {
                            Notification.addNotification({message: noneSelectedMsg, type: 'error'});
                            return;
                        }
                        Ajax.call([{
                            methodname: 'local_learningprism_request_formats',
                            args: {
                                cmid: instance.cmid,
                                formats: formatsSel,
                                personal: true,
                                printbackground: $modal.find('[name="lpbackground"]').val() || '',
                                font: $modal.find('[name="lpfont"]').val() || ''
                            }
                        }])[0].then(function(result) {
                            updateIconState(instance.cmid, result.state);
                            if (typeof $modal.modal === 'function') {
                                $modal.modal('hide');
                            } else {
                                $modal.remove();
                            }
                            return Str.get_string('modal:statusqueued', 'local_learningprism');
                        }).then(function(msg) {
                            Notification.addNotification({message: msg, type: 'info'});
                        }).catch(Notification.exception);
                    });

                    $modal.on('click', '[data-action="lp-download"]', function(e) {
                        e.preventDefault();
                        var format = $(e.currentTarget).attr('data-format') || '';
                        Ajax.call([{
                            methodname: 'local_learningprism_get_download_url',
                            args: {cmid: instance.cmid, format: format, personal: false}
                        }])[0].then(function(result) {
                            (result.files || []).forEach(function(file) {
                                window.open(file.url, '_blank');
                            });
                        }).catch(Notification.exception);
                    });

                    $modal.on('click', '[data-action="lp-download-personal"]', function(e) {
                        e.preventDefault();
                        var format = $(e.currentTarget).attr('data-format') || '';
                        Ajax.call([{
                            methodname: 'local_learningprism_get_download_url',
                            args: {cmid: instance.cmid, format: format, personal: true}
                        }])[0].then(function(result) {
                            (result.files || []).forEach(function(file) {
                                window.open(file.url, '_blank');
                            });
                        }).catch(Notification.exception);
                    });

                    $modal.on('click', '[data-action="lp-pregenerate"]', function(e) {
                        e.preventDefault();
                        Ajax.call([{
                            methodname: 'local_learningprism_pregenerate_formats',
                            args: {
                                courseid: pageCourseId,
                                cmids: [instance.cmid],
                                formats: selectedFormats($modal[0])
                            }
                        }])[0].then(function() {
                            updateIconState(instance.cmid, 'processing');
                            if (typeof $modal.modal === 'function') {
                                $modal.modal('hide');
                            } else {
                                $modal.remove();
                            }
                            return Str.get_string('modal:statusqueued', 'local_learningprism');
                        }).then(function(msg) {
                            Notification.addNotification({message: msg, type: 'info'});
                        }).catch(Notification.exception);
                    });

                    $modal.on('hidden.bs.modal', function() {
                        $modal.remove();
                    });
                }).catch(Notification.exception);
            });
            });
        }).catch(Notification.exception);
    };

    var injectControl = function(instance, canManage) {
        if (document.querySelector('[data-lp-cmid="' + instance.cmid + '"]')) {
            return true;
        }
        var anchor = findActivityNode(instance);
        if (!anchor) {
            return false;
        }
        Templates.render('local_learningprism/control_icon', {
            cmid: instance.cmid,
            state: instance.state,
            modname: instance.modname,
            title: stateTitle(instance.state)
        }).then(function(html, js) {
            Templates.appendNodeContents(anchor, html, js);
            var btn = document.querySelector('[data-lp-cmid="' + instance.cmid + '"]');
            if (btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    openModal(instance, canManage);
                });
            }
        }).catch(Notification.exception);
        return true;
    };

    var init = function(courseId, canManage) {
        pageCourseId = courseId;
        var tryInject = function(instances, attempt) {
            var pending = [];
            instances.forEach(function(instance) {
                if (!injectControl(instance, canManage)) {
                    pending.push(instance);
                }
            });
            // Course formats may render activities after first paint (Snap/Boost).
            if (pending.length && attempt < 8) {
                window.setTimeout(function() {
                    tryInject(pending, attempt + 1);
                }, 400);
            }
        };

        Str.get_strings([
            {key: 'icon:request', component: 'local_learningprism'},
            {key: 'icon:processing', component: 'local_learningprism'},
            {key: 'icon:download', component: 'local_learningprism'},
            {key: 'icon:error', component: 'local_learningprism'}
        ]).then(function(strings) {
            stateTitles.request = strings[0];
            stateTitles.processing = strings[1];
            stateTitles.download = strings[2];
            stateTitles.error = strings[3];
            return Ajax.call([{
                methodname: 'local_learningprism_get_course_instances',
                args: {courseid: courseId}
            }])[0];
        }).then(function(response) {
            tryInject(response.instances || [], 0);
        }).catch(Notification.exception);
    };

    return {init: init};
});
