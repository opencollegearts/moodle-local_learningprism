// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

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
    var courseInstances = [];
    var injecting = {};
    var injectTimer = null;
    var observerStarted = false;
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
        var buttons = document.querySelectorAll('[data-lp-cmid="' + cmid + '"]');
        var i;
        for (i = 0; i < buttons.length; i++) {
            var btn = buttons[i];
            btn.setAttribute('data-lp-state', state);
            btn.classList.remove('lp-state-request', 'lp-state-processing', 'lp-state-download', 'lp-state-error');
            btn.classList.add('lp-state-' + state);
            btn.setAttribute('title', stateTitle(state));
            btn.setAttribute('aria-label', stateTitle(state));
        }
    };

    var isCourseIndexNode = function(el) {
        return !!(el && el.closest && el.closest('#courseindex, .courseindex, [data-region="courseindex"], #toc-searchables'));
    };

    /**
     * Activity card in the course content column (never the course index).
     *
     * @param {string} cmid
     * @return {Element|null}
     */
    var findActivityRoot = function(cmid) {
        var selectors = [
            '#module-' + cmid,
            'li.activity[data-id="' + cmid + '"]',
            'li.activity-wrapper[data-id="' + cmid + '"]',
            '.activity[data-cmid="' + cmid + '"]',
            '[data-for="cmitem"][data-id="' + cmid + '"]',
            'li.snap-activity#module-' + cmid,
            'li.snap-resource#module-' + cmid,
            'li.modtype_label[data-id="' + cmid + '"]',
            'li.modtype_page[data-id="' + cmid + '"]'
        ];
        var i;
        var j;
        for (i = 0; i < selectors.length; i++) {
            var found = document.querySelectorAll(selectors[i]);
            for (j = 0; j < found.length; j++) {
                if (!isCourseIndexNode(found[j])) {
                    return found[j];
                }
            }
        }
        return null;
    };

    /**
     * Card container so the control can sit in the top-right of the activity.
     *
     * @param {Object} instance
     * @return {Element|null}
     */
    var findCardNode = function(instance) {
        var activity = findActivityRoot(String(instance.cmid));
        if (!activity) {
            return null;
        }
        var containers = [
            '.activity-item',
            '.snap-header-card',
            '.snap-asset-content',
            '.asset-wrapper'
        ];
        var i;
        for (i = 0; i < containers.length; i++) {
            var node = activity.querySelector(containers[i]);
            if (node) {
                return node;
            }
        }
        return activity;
    };

    /**
     * Course index / Snap contents entries for this CM.
     *
     * @param {Object} instance
     * @return {Element[]}
     */
    var findNavNodes = function(instance) {
        var cmid = String(instance.cmid);
        var nodes = [];
        var indexItem = document.querySelector('#course-index-cm-' + cmid) ||
            document.querySelector('.courseindex-item[data-for="cm"][data-id="' + cmid + '"]');
        if (indexItem) {
            nodes.push(indexItem);
        }
        var tocItem = document.querySelector('#toc-searchables li[data-id="' + cmid + '"]');
        if (tocItem) {
            nodes.push(tocItem);
        }
        return nodes;
    };

    var courseIndexPresent = function() {
        return !!(document.querySelector('#courseindex') ||
            document.querySelector('[data-region="courseindex"]') ||
            document.querySelector('#toc-searchables'));
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

    var bindControlButton = function(anchor, instance, canManage) {
        var btn = anchor.querySelector('[data-lp-cmid="' + instance.cmid + '"]');
        if (!btn || btn.getAttribute('data-lp-bound') === '1') {
            return;
        }
        btn.setAttribute('data-lp-bound', '1');
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            openModal(instance, canManage);
        });
    };

    var injectAt = function(anchor, instance, canManage, place) {
        if (!anchor) {
            return false;
        }
        if (anchor.querySelector('[data-lp-cmid="' + instance.cmid + '"]')) {
            bindControlButton(anchor, instance, canManage);
            return true;
        }
        var key = instance.cmid + ':' + place + ':' + (anchor.id || '');
        if (injecting[key]) {
            return true;
        }
        injecting[key] = true;
        if (place === 'card') {
            anchor.classList.add('lp-has-prism-control');
        }
        Templates.render('local_learningprism/control_icon', {
            cmid: instance.cmid,
            state: instance.state,
            modname: instance.modname,
            title: stateTitle(instance.state),
            place: place
        }).then(function(html, js) {
            if (!anchor.querySelector('[data-lp-cmid="' + instance.cmid + '"]')) {
                Templates.appendNodeContents(anchor, html, js);
            }
            bindControlButton(anchor, instance, canManage);
            injecting[key] = false;
        }).catch(function(ex) {
            injecting[key] = false;
            Notification.exception(ex);
        });
        return true;
    };

    var injectControl = function(instance, canManage) {
        var card = findCardNode(instance);
        var navs = findNavNodes(instance);
        var waiting = false;

        if (card) {
            injectAt(card, instance, canManage, 'card');
        } else {
            waiting = true;
        }

        if (navs.length) {
            navs.forEach(function(nav) {
                injectAt(nav, instance, canManage, 'nav');
            });
        } else if (courseIndexPresent()) {
            waiting = true;
        }

        return !waiting;
    };

    var tryInject = function(instances, attempt, canManage, retry) {
        var pending = [];
        instances.forEach(function(instance) {
            if (!injectControl(instance, canManage)) {
                pending.push(instance);
            }
        });
        // Course formats may render activities after first paint (Snap/Boost).
        if (retry !== false && pending.length && attempt < 8) {
            window.setTimeout(function() {
                tryInject(pending, attempt + 1, canManage, true);
            }, 400);
        }
    };

    var scheduleInject = function(canManage) {
        if (!courseInstances.length) {
            return;
        }
        if (injectTimer) {
            window.clearTimeout(injectTimer);
        }
        injectTimer = window.setTimeout(function() {
            injectTimer = null;
            tryInject(courseInstances, 0, canManage, false);
        }, 150);
    };

    /**
     * Snap loads later sections on demand (hashchange + fragment). Re-run
     * injection when those nodes appear.
     *
     * @param {boolean} canManage
     */
    var watchForSections = function(canManage) {
        if (observerStarted) {
            return;
        }
        observerStarted = true;
        window.addEventListener('hashchange', function() {
            scheduleInject(canManage);
        });
        window.addEventListener('popstate', function() {
            scheduleInject(canManage);
        });
        if (typeof MutationObserver === 'undefined') {
            return;
        }
        var observer = new MutationObserver(function() {
            scheduleInject(canManage);
        });
        var selectors = [
            'ul.sections',
            '#region-main',
            '.course-content',
            '#courseindex',
            '[data-region="courseindex"]'
        ];
        var i;
        var observed = false;
        for (i = 0; i < selectors.length; i++) {
            var root = document.querySelector(selectors[i]);
            if (root) {
                observer.observe(root, {childList: true, subtree: true});
                observed = true;
            }
        }
        if (!observed && document.body) {
            observer.observe(document.body, {childList: true, subtree: true});
        }
    };

    var init = function(courseId, canManage) {
        pageCourseId = courseId;

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
            courseInstances = response.instances || [];
            watchForSections(canManage);
            tryInject(courseInstances, 0, canManage, true);
        }).catch(Notification.exception);
    };

    return {init: init};
});
