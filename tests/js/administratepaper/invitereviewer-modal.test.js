'use strict';

// ---------------------------------------------------------------------------
// Globals and mocks required before requiring the module
// ---------------------------------------------------------------------------

global.translate = jest.fn((s) => s);
global.isEmail = jest.fn((val) => val && val.includes('@'));
global.isISOdate = jest.fn((val) => /^\d{4}-\d{2}-\d{2}$/.test(val));
global.isValidDate = jest.fn((val) => true);
global.dateIsBetween = jest.fn((val, min, max) => true);
global.getLocaleDate = jest.fn((val) => val);
global.getLoader = jest.fn().mockReturnValue('<span class="loader"></span>');
global.nl2br = jest.fn((val) => val);
global.createUserAutocomplete = jest.fn().mockReturnValue({ inputId: 'autocomplete' });
global.clearErrors = jest.fn();

// Mock TinyMCE
const mockTinyMCEEditor = {
    getContent: jest.fn().mockReturnValue('Some content %%INVITATION_URL%%'),
    setContent: jest.fn(),
    remove: jest.fn(),
};
global.tinymce = {
    get: jest.fn().mockReturnValue(mockTinyMCEEditor),
};
global.tinyMCE = {
    triggerSave: jest.fn(),
};
global.__initMCE = jest.fn();

// Mock global variables expected by the script
global.uid = null;
global.reviewers = [];
global.allJsReviewers = {};
global.siteLocale = 'fr';
global.defaultLocale = 'fr';
global.locale = 'fr';
global.available_languages = { fr: 'Français', en: 'English' };
global.templates = {
    1: { subject: { fr: 'Sujet réinviter', en: 'Subject reinvite' }, body: { fr: 'Corps réinviter', en: 'Body reinvite' } },
    2: { subject: { fr: 'Sujet nouveau', en: 'Subject new' }, body: { fr: 'Corps nouveau', en: 'Body new' } },
    3: { subject: { fr: 'Sujet step1', en: 'Subject step1' }, body: { fr: 'Corps step1', en: 'Body step1' } }
};
global.paper = { id: 42, title: { fr: 'Titre de l\'article', en: 'Paper Title' }, paperId: '12345' };
global.review = { invitation_deadline: '7 days', rating_deadline: '2026-07-22', code: 'REV_CODE', name: 'Journal Name' };
global.allAuthors = 'Author A, Author B';
global.editor = { full_name: 'Editor Name', email: 'editor@example.com' };
global.contributor = { full_name: 'Contributor Name', email: 'contributor@example.com', user_name: 'contrib_user', screen_name: 'ContribScreen' };
global.ignore_list = [];

// ---------------------------------------------------------------------------
// JQuery Mock linked to real JSDOM tree
// ---------------------------------------------------------------------------

let ajaxSuccessPayload = {};
let ajaxShouldSucceed = true;

const jQueryMock = (selector) => {
    if (typeof selector === 'function') {
        selector();
        return jQueryMock;
    }
    
    if (selector === document) {
        return {
            ready: (cb) => cb(),
        };
    }

    let elements = [];
    if (typeof selector === 'string') {
        if (selector.startsWith('#') && !selector.includes(' ') && !selector.includes('.') && !selector.includes('[') && !selector.includes(':')) {
            const el = document.getElementById(selector.substring(1));
            if (el) elements = [el];
        } else {
            try {
                elements = Array.from(document.querySelectorAll(selector));
            } catch (e) {
                elements = [];
            }
        }
    } else if (selector instanceof HTMLElement) {
        elements = [selector];
    } else if (selector && selector.jquery) {
        return selector;
    } else if (Array.isArray(selector)) {
        elements = selector;
    } else if (selector && typeof selector.toArray === 'function') {
        elements = selector.toArray();
    } else if (selector) {
        elements = [selector];
    }

    const wrap = (els) => jQueryMock(els);

    const jq = {
        jquery: '3.6.0',
        length: elements.length,
        val: function (newVal) {
            if (newVal !== undefined) {
                elements.forEach(el => { el.value = newVal; });
                return this;
            }
            return elements[0] ? elements[0].value : '';
        },
        attr: function (name, val) {
            if (val !== undefined) {
                elements.forEach(el => el.setAttribute(name, val));
                return this;
            }
            return elements[0] ? elements[0].getAttribute(name) : undefined;
        },
        prop: function (name, val) {
            if (val !== undefined) {
                elements.forEach(el => { el[name] = val; });
                return this;
            }
            return elements[0] ? elements[0][name] : undefined;
        },
        show: function () {
            elements.forEach(el => { el.style.display = ''; el.hidden = false; });
            return this;
        },
        hide: function () {
            elements.forEach(el => { el.style.display = 'none'; });
            return this;
        },
        fadeIn: function () {
            elements.forEach(el => { el.style.display = ''; el.hidden = false; });
            return this;
        },
        fadeOut: function () {
            elements.forEach(el => { el.style.display = 'none'; });
            return this;
        },
        empty: function () {
            elements.forEach(el => { el.innerHTML = ''; });
            return this;
        },
        append: function (content) {
            elements.forEach(el => {
                if (typeof content === 'string') {
                    el.insertAdjacentHTML('beforeend', content);
                } else if (content instanceof HTMLElement) {
                    el.appendChild(content);
                } else if (content && content.jquery) {
                    content.each((i, e) => el.appendChild(e));
                }
            });
            return this;
        },
        html: function (content) {
            if (content !== undefined) {
                elements.forEach(el => { el.innerHTML = content; });
                return this;
            }
            return elements[0] ? elements[0].innerHTML : '';
        },
        text: function (content) {
            if (content !== undefined) {
                elements.forEach(el => { el.textContent = content; });
                return this;
            }
            return elements[0] ? elements[0].textContent : '';
        },
        on: function (events, selector, handler) {
            const evts = events.split(' ');
            const actualHandler = typeof selector === 'function' ? selector : handler;
            const sel = typeof selector === 'string' ? selector : null;

            elements.forEach(el => {
                evts.forEach(evt => {
                    el.addEventListener(evt, (e) => {
                        if (sel) {
                            if (e.target.matches(sel)) {
                                actualHandler.call(e.target, e);
                            }
                        } else {
                            actualHandler.call(el, e);
                        }
                    });
                });
            });
            return this;
        },
        off: function (events) {
            return this;
        },
        click: function (handler) {
            if (typeof handler === 'function') {
                return this.on('click', handler);
            }
            elements.forEach(el => el.click());
            return this;
        },
        change: function (handler) {
            if (typeof handler === 'function') {
                return this.on('change', handler);
            }
            elements.forEach(el => {
                const event = new Event('change', { bubbles: true });
                el.dispatchEvent(event);
            });
            return this;
        },
        trigger: function (event) {
            const evtName = typeof event === 'string' ? event : event.type;
            elements.forEach(el => {
                const e = new Event(evtName, { bubbles: true });
                el.dispatchEvent(e);
            });
            return this;
        },
        serialize: function () {
            if (elements[0] && elements[0].tagName === 'FORM') {
                const formData = new FormData(elements[0]);
                const params = new URLSearchParams();
                for (const [key, value] of formData.entries()) {
                    params.append(key, value);
                }
                return params.toString();
            }
            return '';
        },
        url: function () {
            return {
                param: (name) => '42'
            };
        },
        dataTable: function (opts) {
            return this;
        },
        tooltip: function () {
            return this;
        },
        each: function (callback) {
            elements.forEach((el, index) => {
                callback.call(el, index, el);
            });
            return this;
        },
        hasClass: function (cls) {
            return elements[0] ? elements[0].classList.contains(cls) : false;
        },
        addClass: function (cls) {
            elements.forEach(el => el.classList.add(cls));
            return this;
        },
        removeClass: function (cls) {
            elements.forEach(el => el.classList.remove(cls));
            return this;
        },
        find: function (sel) {
            let found = [];
            elements.forEach(el => {
                found = found.concat(Array.from(el.querySelectorAll(sel)));
            });
            return wrap(found);
        },
        closest: function (sel) {
            let found = [];
            elements.forEach(el => {
                const c = el.closest(sel);
                if (c) found.push(c);
            });
            return wrap(found);
        },
        is: function (sel) {
            if (sel === ':visible') {
                return elements[0] ? (elements[0].style.display !== 'none' && !elements[0].hidden) : false;
            }
            return false;
        },
    };

    elements.forEach((el, i) => {
        jq[i] = el;
    });

    return jq;
};

jQueryMock.trim = (str) => (str || '').trim();
jQueryMock.type = (obj) => typeof obj;

jQueryMock.ajax = jest.fn().mockImplementation((options) => {
    jQueryMock.ajax.lastCall = options;
    const promise = {
        done: jest.fn().mockImplementation((cb) => {
            if (ajaxShouldSucceed) {
                cb(ajaxSuccessPayload);
            }
            return promise;
        }),
        fail: jest.fn().mockImplementation((cb) => {
            if (!ajaxShouldSucceed) {
                cb(null, 'error');
            }
            return promise;
        }),
    };
    return promise;
});

global.$ = jQueryMock;
global.jQuery = jQueryMock;

// ---------------------------------------------------------------------------
// JSDOM preparation function
// ---------------------------------------------------------------------------

function prepareDOM() {
    document.body.innerHTML = `
        <div id="step-1"></div>
        <div id="step-2">
            <div class="panel-title">Inviter ce relecteur</div>
        </div>
        <div id="alert_exist_login"></div>
        <input id="existing-reviewer" value="1" />
        <input id="autocomplete" />
        <form id="tmp-user-form"></form>
        <input id="deadline-id" attr-mindate="2026-06-01" attr-maxdate="2026-12-31" value="2026-07-22" />
        <input id="email" name="email" value="test@example.com" />
        <input id="firstname" name="firstname" value="John" />
        <input id="lastname" name="lastname" value="Doe" />
        <button id="next" class="btn btn-default"></button>
        <div id="homonym_users"></div>
        <button id="new_user_button"></button>
        <div id="new-user"></div>
        <div id="required_tmp_user"></div>
        <div id="known-reviewers-body"></div>
        <div id="invitereviewer_guideline"></div>
        <select id="user_lang">
            <option value="fr">French</option>
            <option value="en">English</option>
        </select>
        <div id="lastname-element"></div>
        <div id="firstname-element"></div>
        <div id="user_lang-element"></div>
        <div id="loading_container"></div>
        <table id="known-reviewers"></table>
        <button id="show-known-reviewers"></button>
        <button id="back-button"></button>
        <form id="invitation-form" action="/journal/administratepaper/savereviewerinvitation"></form>
        <div id="reviewers"></div>
        <div id="history"><div class="panel-body"></div></div>
        <input id="recipient" />
        <input id="subject" />
        <textarea id="body"></textarea>
        <div class="form-errors"></div>
    `;
}

// ---------------------------------------------------------------------------
// Unit tests
// ---------------------------------------------------------------------------

describe('invitereviewer-modal.js Test Suite', () => {
    let moduleUnderTest;

    beforeEach(() => {
        jest.resetModules();
        prepareDOM();
        ajaxShouldSucceed = true;
        ajaxSuccessPayload = {};
        jQueryMock.ajax.mockClear();
        global.translate.mockImplementation((s) => s);
        
        moduleUnderTest = require('../../../public/js/administratepaper/invitereviewer-modal');
    });

    afterEach(() => {
        jest.clearAllMocks();
    });

    describe('createUser', () => {
        test('should successfully map backend CAS user to frontend reviewer structure', () => {
            const oUser = {
                FIRSTNAME: 'Alice',
                LASTNAME: 'Bob',
                EMAIL: 'alice.bob@example.com',
                USERNAME: 'abob',
                UID: '4242'
            };
            $('#user_lang').val('en');
            const user = moduleUnderTest.createUser(oUser);

            expect(user).toEqual({
                email: 'alice.bob@example.com',
                full_name: 'Alice Bob',
                locale: 'en',
                firstname: 'Alice',
                lastname: 'Bob',
                user_name: 'abob',
                id: '4242'
            });
        });

        test('should fallback to empty string for full_name when firstname and lastname are missing', () => {
            const oUser = {
                EMAIL: 'no.name@example.com',
                USERNAME: 'noname',
                UID: '0'
            };
            const user = moduleUnderTest.createUser(oUser);
            expect(user.full_name).toBe('');
        });
    });

    describe('translateInvitationDeadline', () => {
        test('should format and translate the deadline string correctly', () => {
            global.translate.mockImplementation((str) => `[${str}]`);
            const translated = moduleUnderTest.translateInvitationDeadline('5 days', 'fr');
            expect(translated).toBe('5 [days]');
            expect(global.translate).toHaveBeenCalledWith('days', 'fr');
        });
    });

    describe('validateTmpFormInvitation', () => {
        test('should return true for valid fields', () => {
            $('#email').val('alice@example.com');
            $('#lastname').val('Doe');
            global.isEmail.mockReturnValue(true);

            expect(moduleUnderTest.validateTmpFormInvitation()).toBe(true);
        });

        test('should fail when email is invalid', () => {
            $('#email').val('invalid-email');
            $('#lastname').val('Doe');
            global.isEmail.mockReturnValue(false);

            expect(moduleUnderTest.validateTmpFormInvitation()).toBe(false);
            expect($('.form-errors .errors').text()).toContain('Veuillez entrer une adresse e-mail valide');
        });

        test('should fail when lastname is empty', () => {
            $('#email').val('alice@example.com');
            $('#lastname').val('   ');
            global.isEmail.mockReturnValue(true);

            expect(moduleUnderTest.validateTmpFormInvitation()).toBe(false);
            expect($('.form-errors .errors').text()).toContain('Veuillez entrer un nom');
        });

        test('should aggregate errors when both are invalid', () => {
            $('#email').val('');
            $('#lastname').val('');
            global.isEmail.mockReturnValue(false);

            expect(moduleUnderTest.validateTmpFormInvitation()).toBe(false);
            expect($('.form-errors .errors').text()).toContain('Veuillez entrer une adresse e-mail valide et un nom');
        });
    });

    describe('validate_step2', () => {
        test('should validate successfully when all fields are correct', () => {
            document.getElementById('invitation-form').style.display = 'block';
            $('#deadline-id').val('2026-07-22');
            global.isISOdate.mockReturnValue(true);
            global.isValidDate.mockReturnValue(true);
            global.dateIsBetween.mockReturnValue(true);
            mockTinyMCEEditor.getContent.mockReturnValue('Hello, please review using %%INVITATION_URL%%. Thanks.');

            expect(moduleUnderTest.validate_step2()).toBe(true);
        });

        test('should fail if the invitation form is hidden', () => {
            document.getElementById('invitation-form').style.display = 'none';
            $('#deadline-id').val('2026-07-22');
            global.isISOdate.mockReturnValue(true);
            global.isValidDate.mockReturnValue(true);
            global.dateIsBetween.mockReturnValue(true);
            mockTinyMCEEditor.getContent.mockReturnValue('%%INVITATION_URL%%');

            expect(moduleUnderTest.validate_step2()).toBe(false);
            expect($('.form-errors .errors').text()).toContain('Veuillez choisir un relecteur');
        });

        test('should fail if deadline is not in ISO format', () => {
            document.getElementById('invitation-form').style.display = 'block';
            $('#deadline-id').val('22/07/2026');
            global.isISOdate.mockReturnValue(false);

            expect(moduleUnderTest.validate_step2()).toBe(false);
            expect($('.form-errors .errors').text()).toContain('AAAA-mm-jj');
        });

        test('should fail if deadline is invalid date', () => {
            document.getElementById('invitation-form').style.display = 'block';
            $('#deadline-id').val('2026-02-31');
            global.isISOdate.mockReturnValue(true);
            global.isValidDate.mockReturnValue(false);

            expect(moduleUnderTest.validate_step2()).toBe(false);
            expect($('.form-errors .errors').text()).toContain("La date limite de relecture n'est pas valide");
        });

        test('should fail if deadline is outside allowed range', () => {
            document.getElementById('invitation-form').style.display = 'block';
            $('#deadline-id').val('2026-05-15');
            global.isISOdate.mockReturnValue(true);
            global.isValidDate.mockReturnValue(true);
            global.dateIsBetween.mockReturnValue(false);

            expect(moduleUnderTest.validate_step2()).toBe(false);
            expect($('.form-errors .errors').text()).toContain('La date limite de relecture doit être comprise entre');
        });

        test('should fail if tinymce body is empty', () => {
            document.getElementById('invitation-form').style.display = 'block';
            $('#deadline-id').val('2026-07-22');
            global.isISOdate.mockReturnValue(true);
            global.isValidDate.mockReturnValue(true);
            global.dateIsBetween.mockReturnValue(true);
            mockTinyMCEEditor.getContent.mockReturnValue('');

            expect(moduleUnderTest.validate_step2()).toBe(false);
            expect($('.form-errors .errors').text()).toContain('Veuillez saisir un message à destination du relecteur');
        });

        test('should fail if tinymce body is missing %%INVITATION_URL%% placeholder', () => {
            document.getElementById('invitation-form').style.display = 'block';
            $('#deadline-id').val('2026-07-22');
            global.isISOdate.mockReturnValue(true);
            global.isValidDate.mockReturnValue(true);
            global.dateIsBetween.mockReturnValue(true);
            mockTinyMCEEditor.getContent.mockReturnValue('Review link here.');

            expect(moduleUnderTest.validate_step2()).toBe(false);
            expect($('.form-errors .errors').text()).toContain('%%INVITATION_URL%%');
        });
    });

    describe('replaceTags', () => {
        test('should replace all placeholders correctly with mock data', () => {
            const reviewer = {
                full_name: 'Rev FullName',
                screen_name: 'RevScreenName',
                user_name: 'rev_username'
            };
            const tpl = 'Deadline: %%RATING_DEADLINE%% | InviteDeadline: %%INVITATION_DEADLINE%% | Code: %%REVIEW_CODE%% | Name: %%REVIEW_NAME%% | Screen: %%RECIPIENT_SCREEN_NAME%% | User: %%RECIPIENT_USERNAME%% | Full: %%RECIPIENT_FULL_NAME%% | Sender: %%SENDER_FULL_NAME%% <%%SENDER_EMAIL%%> | ID: %%ARTICLE_ID%% | UUID: %%PERMANENT_ARTICLE_ID%% | Title: %%ARTICLE_TITLE%% | Contributor: %%CONTRIBUTOR_FULL_NAME%% <%%CONTRIBUTOR_EMAIL%%> | Authors: %%AUTHORS_NAMES%%';

            global.getLocaleDate.mockReturnValue('2026-07-22');

            const result = moduleUnderTest.replaceTags(tpl, reviewer, 'fr');

            expect(result).toBe('Deadline: 2026-07-22 | InviteDeadline: 7 days | Code: REV_CODE | Name: Journal Name | Screen: RevScreenName | User: rev_username | Full: Rev FullName | Sender: Editor Name <editor@example.com> | ID: 42 | UUID: 12345 | Title: Titre de l\'article | Contributor: Contributor Name <contributor@example.com> | Authors: Author A, Author B');
        });

        test('should use full_name if screen_name is not defined', () => {
            const reviewer = {
                full_name: 'Rev FullName',
                user_name: 'rev_username'
            };
            const tpl = '%%RECIPIENT_SCREEN_NAME%%';
            const result = moduleUnderTest.replaceTags(tpl, reviewer, 'fr');
            expect(result).toBe('Rev FullName');
        });
    });

    describe('step UI Transitions', () => {
        test('step1 shows first step and hides second', () => {
            const step1 = document.getElementById('step-1');
            const step2 = document.getElementById('step-2');
            step1.style.display = 'none';
            step2.style.display = 'block';

            moduleUnderTest.step1();

            expect(step1.style.display).not.toBe('none');
            expect(step2.style.display).toBe('none');
            expect(document.getElementById('invitereviewer_guideline').hidden).toBe(false);
        });

        test('step2 shows second step and hides first', () => {
            const step1 = document.getElementById('step-1');
            const step2 = document.getElementById('step-2');
            step1.style.display = 'block';
            step2.style.display = 'none';

            moduleUnderTest.step2();

            expect(step1.style.display).toBe('none');
            expect(step2.style.display).not.toBe('none');
            expect(document.getElementById('invitereviewer_guideline').style.display).toBe('none');
        });

        test('hideElements hides name and language fields', () => {
            const last = document.getElementById('lastname-element');
            const first = document.getElementById('firstname-element');
            const lang = document.getElementById('user_lang-element');

            moduleUnderTest.hideElements();

            expect(last.style.display).toBe('none');
            expect(first.style.display).toBe('none');
            expect(lang.style.display).toBe('none');
        });

        test('showElements shows name and language fields', () => {
            const last = document.getElementById('lastname-element');
            const first = document.getElementById('firstname-element');
            const lang = document.getElementById('user_lang-element');
            last.style.display = 'none';
            first.style.display = 'none';
            lang.style.display = 'none';

            moduleUnderTest.showElements();

            expect(last.style.display).not.toBe('none');
            expect(first.style.display).not.toBe('none');
            expect(lang.style.display).not.toBe('none');
        });
    });

    describe('replaceClass', () => {
        test('does not replace class when canReplaceClass is false', () => {
            const btn = document.getElementById('next');
            btn.className = 'btn-default';
            
            moduleUnderTest.replaceClass($(btn), 'btn-default', 'btn-success');
            expect(btn.classList.contains('btn-default')).toBe(true);
            expect(btn.classList.contains('btn-success')).toBe(false);
        });

        test('replaces class when canReplaceClass is true', () => {
            const btn = document.getElementById('next');
            btn.className = 'btn-default';
            
            // Set canReplaceClass = true
            moduleUnderTest.checkDuplicateUser([], []);

            moduleUnderTest.replaceClass($(btn), 'btn-default', 'btn-success');
            expect(btn.classList.contains('btn-default')).toBe(false);
            expect(btn.classList.contains('btn-success')).toBe(true);
        });
    });

    describe('findUserByMail', () => {
        test('should make a POST request with the email', () => {
            moduleUnderTest.findUserByMail('test@example.com');

            expect(jQueryMock.ajax).toHaveBeenCalledWith({
                url: '/user/ajaxfindusersbymail',
                type: 'POST',
                dataType: 'json',
                data: { email: 'test@example.com' }
            });
        });
    });

    describe('findUsers', () => {
        test('should make a POST request with the lastname', () => {
            moduleUnderTest.findUsers('Smith');

            expect(jQueryMock.ajax).toHaveBeenCalledWith({
                url: '/user/findusersbyfirstnameandname',
                type: 'POST',
                dataType: 'json',
                data: { lastName: 'Smith' }
            });
        });
    });

    describe('checkDuplicateUser', () => {
        test('should call displayCcsdUsers and displayDuplicateUsers when keys found', () => {
            const data = [{ UID: '1', USERNAME: 'user1' }];
            const keys = ['1'];
            ajaxSuccessPayload = '<div>duplicate list</div>';

            moduleUnderTest.checkDuplicateUser(data, keys);

            expect(jQueryMock.ajax).toHaveBeenCalled();
            expect($('#alert_exist_login').html()).toBe('<div>duplicate list</div>');
            expect($('#next').is(':visible')).toBe(false);
        });
    });

    describe('submit', () => {
        test('should send serialized form data when step 2 validation passes', () => {
            document.getElementById('invitation-form').style.display = 'block';
            $('#deadline-id').val('2026-07-22');
            global.isISOdate.mockReturnValue(true);
            global.isValidDate.mockReturnValue(true);
            global.dateIsBetween.mockReturnValue(true);
            mockTinyMCEEditor.getContent.mockReturnValue('%%INVITATION_URL%%');

            moduleUnderTest.submit();

            expect(jQueryMock.ajax).toHaveBeenCalled();
            expect(jQueryMock.ajax.lastCall.type).toBe('POST');
        });
    });
});
