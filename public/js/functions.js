var $modal_box;
var $modal_body;
var $modal_footer;
var $modal_button;
var $modal_form;

var openedPopover;

$(document).ready(function () {

    $modal_box = $('#modal-box');
    $modal_body = $modal_box.find('.modal-body');
    $modal_footer = $modal_box.find('.modal-footer');
    $modal_button = $('#submit-modal');
    $modal_form = $modal_box.find('form');

    // fix for making TinyMCE dialog boxes work with bootstrap modals
    $(document).on('focusin', function (e) {
        if ($(e.target).closest(".mce-window").length) {
            e.stopImmediatePropagation();
        }
    });

    // block close button
    $('.collapsable').each(function () {
        applyCollapse(this);
    });

    $('.collapse').on({
        shown: function () {
            $(this).css('overflow', 'visible');
        },
        hide: function () {
            $(this).css('overflow', 'hidden');
        }
    });

    // tooltips activation
    activateTooltips();

    // create modal structure
    if ($('.modal-opener').length && !modalStructureExists()) {
        createModalStructure();
    }

    // modal activation
    $(document).on('click', 'a.modal-opener', function (e) {
        e.preventDefault();
        $('.popover-link').popover('destroy');
        openModal($(this).attr('href'), $(this).attr('title'), $(this).data(), e);
        return false;
    });

    // close popovers when clicking somewhere in the page
    $(document).on('click', function (e) {
        let isPopover = $(e.target).is('.popover-link');
        let inPopover = $(e.target).closest('.popover').length > 0;
        if (!isPopover && !inPopover) {
            openedPopover = null;
            $('.popover-link').popover('destroy');
        }
    });
});

// fetch first element of an array or an object
function getFirstOf(data) {
    for (var key in data)
        return data[key];
}

function readabeBytes(bytes, locale) {
    var s = (locale === 'fr') ? ['octets', 'Ko', 'Mo', 'Go', 'To', 'Po'] : ['bytes', 'KB', 'MB', 'GB', 'TB', 'PB'];
    var e = Math.floor(Math.log(bytes) / Math.log(1024));
    return Math.round(bytes / Math.pow(1024, e)) + " " + s[e];
}

//Tableau de remplacement des accents
var stripAccentsMap = [
    {
        'base': 'A',
        'letters': /[\u0041\u24B6\uFF21\u00C0\u00C1\u00C2\u1EA6\u1EA4\u1EAA\u1EA8\u00C3\u0100\u0102\u1EB0\u1EAE\u1EB4\u1EB2\u0226\u01E0\u00C4\u01DE\u1EA2\u00C5\u01FA\u01CD\u0200\u0202\u1EA0\u1EAC\u1EB6\u1E00\u0104\u023A\u2C6F]/g
    },
    {'base': 'AA', 'letters': /[\uA732]/g},
    {'base': 'AE', 'letters': /[\u00C6\u01FC\u01E2]/g},
    {'base': 'AO', 'letters': /[\uA734]/g},
    {'base': 'AU', 'letters': /[\uA736]/g},
    {'base': 'AV', 'letters': /[\uA738\uA73A]/g},
    {'base': 'AY', 'letters': /[\uA73C]/g},
    {'base': 'B', 'letters': /[\u0042\u24B7\uFF22\u1E02\u1E04\u1E06\u0243\u0182\u0181]/g},
    {'base': 'C', 'letters': /[\u0043\u24B8\uFF23\u0106\u0108\u010A\u010C\u00C7\u1E08\u0187\u023B\uA73E]/g},
    {'base': 'D', 'letters': /[\u0044\u24B9\uFF24\u1E0A\u010E\u1E0C\u1E10\u1E12\u1E0E\u0110\u018B\u018A\u0189\uA779]/g},
    {'base': 'DZ', 'letters': /[\u01F1\u01C4]/g},
    {'base': 'Dz', 'letters': /[\u01F2\u01C5]/g},
    {
        'base': 'E',
        'letters': /[\u0045\u24BA\uFF25\u00C8\u00C9\u00CA\u1EC0\u1EBE\u1EC4\u1EC2\u1EBC\u0112\u1E14\u1E16\u0114\u0116\u00CB\u1EBA\u011A\u0204\u0206\u1EB8\u1EC6\u0228\u1E1C\u0118\u1E18\u1E1A\u0190\u018E]/g
    },
    {'base': 'F', 'letters': /[\u0046\u24BB\uFF26\u1E1E\u0191\uA77B]/g},
    {
        'base': 'G',
        'letters': /[\u0047\u24BC\uFF27\u01F4\u011C\u1E20\u011E\u0120\u01E6\u0122\u01E4\u0193\uA7A0\uA77D\uA77E]/g
    },
    {'base': 'H', 'letters': /[\u0048\u24BD\uFF28\u0124\u1E22\u1E26\u021E\u1E24\u1E28\u1E2A\u0126\u2C67\u2C75\uA78D]/g},
    {
        'base': 'I',
        'letters': /[\u0049\u24BE\uFF29\u00CC\u00CD\u00CE\u0128\u012A\u012C\u0130\u00CF\u1E2E\u1EC8\u01CF\u0208\u020A\u1ECA\u012E\u1E2C\u0197]/g
    },
    {'base': 'J', 'letters': /[\u004A\u24BF\uFF2A\u0134\u0248]/g},
    {'base': 'K', 'letters': /[\u004B\u24C0\uFF2B\u1E30\u01E8\u1E32\u0136\u1E34\u0198\u2C69\uA740\uA742\uA744\uA7A2]/g},
    {
        'base': 'L',
        'letters': /[\u004C\u24C1\uFF2C\u013F\u0139\u013D\u1E36\u1E38\u013B\u1E3C\u1E3A\u0141\u023D\u2C62\u2C60\uA748\uA746\uA780]/g
    },
    {'base': 'LJ', 'letters': /[\u01C7]/g},
    {'base': 'Lj', 'letters': /[\u01C8]/g},
    {'base': 'M', 'letters': /[\u004D\u24C2\uFF2D\u1E3E\u1E40\u1E42\u2C6E\u019C]/g},
    {
        'base': 'N',
        'letters': /[\u004E\u24C3\uFF2E\u01F8\u0143\u00D1\u1E44\u0147\u1E46\u0145\u1E4A\u1E48\u0220\u019D\uA790\uA7A4]/g
    },
    {'base': 'NJ', 'letters': /[\u01CA]/g},
    {'base': 'Nj', 'letters': /[\u01CB]/g},
    {
        'base': 'O',
        'letters': /[\u004F\u24C4\uFF2F\u00D2\u00D3\u00D4\u1ED2\u1ED0\u1ED6\u1ED4\u00D5\u1E4C\u022C\u1E4E\u014C\u1E50\u1E52\u014E\u022E\u0230\u00D6\u022A\u1ECE\u0150\u01D1\u020C\u020E\u01A0\u1EDC\u1EDA\u1EE0\u1EDE\u1EE2\u1ECC\u1ED8\u01EA\u01EC\u00D8\u01FE\u0186\u019F\uA74A\uA74C]/g
    },
    {'base': 'OI', 'letters': /[\u01A2]/g},
    {'base': 'OO', 'letters': /[\uA74E]/g},
    {'base': 'OU', 'letters': /[\u0222]/g},
    {'base': 'P', 'letters': /[\u0050\u24C5\uFF30\u1E54\u1E56\u01A4\u2C63\uA750\uA752\uA754]/g},
    {'base': 'Q', 'letters': /[\u0051\u24C6\uFF31\uA756\uA758\u024A]/g},
    {
        'base': 'R',
        'letters': /[\u0052\u24C7\uFF32\u0154\u1E58\u0158\u0210\u0212\u1E5A\u1E5C\u0156\u1E5E\u024C\u2C64\uA75A\uA7A6\uA782]/g
    },
    {
        'base': 'S',
        'letters': /[\u0053\u24C8\uFF33\u1E9E\u015A\u1E64\u015C\u1E60\u0160\u1E66\u1E62\u1E68\u0218\u015E\u2C7E\uA7A8\uA784]/g
    },
    {
        'base': 'T',
        'letters': /[\u0054\u24C9\uFF34\u1E6A\u0164\u1E6C\u021A\u0162\u1E70\u1E6E\u0166\u01AC\u01AE\u023E\uA786]/g
    },
    {'base': 'TZ', 'letters': /[\uA728]/g},
    {
        'base': 'U',
        'letters': /[\u0055\u24CA\uFF35\u00D9\u00DA\u00DB\u0168\u1E78\u016A\u1E7A\u016C\u00DC\u01DB\u01D7\u01D5\u01D9\u1EE6\u016E\u0170\u01D3\u0214\u0216\u01AF\u1EEA\u1EE8\u1EEE\u1EEC\u1EF0\u1EE4\u1E72\u0172\u1E76\u1E74\u0244]/g
    },
    {'base': 'V', 'letters': /[\u0056\u24CB\uFF36\u1E7C\u1E7E\u01B2\uA75E\u0245]/g},
    {'base': 'VY', 'letters': /[\uA760]/g},
    {'base': 'W', 'letters': /[\u0057\u24CC\uFF37\u1E80\u1E82\u0174\u1E86\u1E84\u1E88\u2C72]/g},
    {'base': 'X', 'letters': /[\u0058\u24CD\uFF38\u1E8A\u1E8C]/g},
    {
        'base': 'Y',
        'letters': /[\u0059\u24CE\uFF39\u1EF2\u00DD\u0176\u1EF8\u0232\u1E8E\u0178\u1EF6\u1EF4\u01B3\u024E\u1EFE]/g
    },
    {'base': 'Z', 'letters': /[\u005A\u24CF\uFF3A\u0179\u1E90\u017B\u017D\u1E92\u1E94\u01B5\u0224\u2C7F\u2C6B\uA762]/g},
    {
        'base': 'a',
        'letters': /[\u0061\u24D0\uFF41\u1E9A\u00E0\u00E1\u00E2\u1EA7\u1EA5\u1EAB\u1EA9\u00E3\u0101\u0103\u1EB1\u1EAF\u1EB5\u1EB3\u0227\u01E1\u00E4\u01DF\u1EA3\u00E5\u01FB\u01CE\u0201\u0203\u1EA1\u1EAD\u1EB7\u1E01\u0105\u2C65\u0250]/g
    },
    {'base': 'aa', 'letters': /[\uA733]/g},
    {'base': 'ae', 'letters': /[\u00E6\u01FD\u01E3]/g},
    {'base': 'ao', 'letters': /[\uA735]/g},
    {'base': 'au', 'letters': /[\uA737]/g},
    {'base': 'av', 'letters': /[\uA739\uA73B]/g},
    {'base': 'ay', 'letters': /[\uA73D]/g},
    {'base': 'b', 'letters': /[\u0062\u24D1\uFF42\u1E03\u1E05\u1E07\u0180\u0183\u0253]/g},
    {'base': 'c', 'letters': /[\u0063\u24D2\uFF43\u0107\u0109\u010B\u010D\u00E7\u1E09\u0188\u023C\uA73F\u2184]/g},
    {'base': 'd', 'letters': /[\u0064\u24D3\uFF44\u1E0B\u010F\u1E0D\u1E11\u1E13\u1E0F\u0111\u018C\u0256\u0257\uA77A]/g},
    {'base': 'dz', 'letters': /[\u01F3\u01C6]/g},
    {
        'base': 'e',
        'letters': /[\u0065\u24D4\uFF45\u00E8\u00E9\u00EA\u1EC1\u1EBF\u1EC5\u1EC3\u1EBD\u0113\u1E15\u1E17\u0115\u0117\u00EB\u1EBB\u011B\u0205\u0207\u1EB9\u1EC7\u0229\u1E1D\u0119\u1E19\u1E1B\u0247\u025B\u01DD]/g
    },
    {'base': 'f', 'letters': /[\u0066\u24D5\uFF46\u1E1F\u0192\uA77C]/g},
    {
        'base': 'g',
        'letters': /[\u0067\u24D6\uFF47\u01F5\u011D\u1E21\u011F\u0121\u01E7\u0123\u01E5\u0260\uA7A1\u1D79\uA77F]/g
    },
    {
        'base': 'h',
        'letters': /[\u0068\u24D7\uFF48\u0125\u1E23\u1E27\u021F\u1E25\u1E29\u1E2B\u1E96\u0127\u2C68\u2C76\u0265]/g
    },
    {'base': 'hv', 'letters': /[\u0195]/g},
    {
        'base': 'i',
        'letters': /[\u0069\u24D8\uFF49\u00EC\u00ED\u00EE\u0129\u012B\u012D\u00EF\u1E2F\u1EC9\u01D0\u0209\u020B\u1ECB\u012F\u1E2D\u0268\u0131]/g
    },
    {'base': 'j', 'letters': /[\u006A\u24D9\uFF4A\u0135\u01F0\u0249]/g},
    {'base': 'k', 'letters': /[\u006B\u24DA\uFF4B\u1E31\u01E9\u1E33\u0137\u1E35\u0199\u2C6A\uA741\uA743\uA745\uA7A3]/g},
    {
        'base': 'l',
        'letters': /[\u006C\u24DB\uFF4C\u0140\u013A\u013E\u1E37\u1E39\u013C\u1E3D\u1E3B\u017F\u0142\u019A\u026B\u2C61\uA749\uA781\uA747]/g
    },
    {'base': 'lj', 'letters': /[\u01C9]/g},
    {'base': 'm', 'letters': /[\u006D\u24DC\uFF4D\u1E3F\u1E41\u1E43\u0271\u026F]/g},
    {
        'base': 'n',
        'letters': /[\u006E\u24DD\uFF4E\u01F9\u0144\u00F1\u1E45\u0148\u1E47\u0146\u1E4B\u1E49\u019E\u0272\u0149\uA791\uA7A5]/g
    },
    {'base': 'nj', 'letters': /[\u01CC]/g},
    {
        'base': 'o',
        'letters': /[\u006F\u24DE\uFF4F\u00F2\u00F3\u00F4\u1ED3\u1ED1\u1ED7\u1ED5\u00F5\u1E4D\u022D\u1E4F\u014D\u1E51\u1E53\u014F\u022F\u0231\u00F6\u022B\u1ECF\u0151\u01D2\u020D\u020F\u01A1\u1EDD\u1EDB\u1EE1\u1EDF\u1EE3\u1ECD\u1ED9\u01EB\u01ED\u00F8\u01FF\u0254\uA74B\uA74D\u0275]/g
    },
    {'base': 'oi', 'letters': /[\u01A3]/g},
    {'base': 'ou', 'letters': /[\u0223]/g},
    {'base': 'oo', 'letters': /[\uA74F]/g},
    {'base': 'p', 'letters': /[\u0070\u24DF\uFF50\u1E55\u1E57\u01A5\u1D7D\uA751\uA753\uA755]/g},
    {'base': 'q', 'letters': /[\u0071\u24E0\uFF51\u024B\uA757\uA759]/g},
    {
        'base': 'r',
        'letters': /[\u0072\u24E1\uFF52\u0155\u1E59\u0159\u0211\u0213\u1E5B\u1E5D\u0157\u1E5F\u024D\u027D\uA75B\uA7A7\uA783]/g
    },
    {
        'base': 's',
        'letters': /[\u0073\u24E2\uFF53\u00DF\u015B\u1E65\u015D\u1E61\u0161\u1E67\u1E63\u1E69\u0219\u015F\u023F\uA7A9\uA785\u1E9B]/g
    },
    {
        'base': 't',
        'letters': /[\u0074\u24E3\uFF54\u1E6B\u1E97\u0165\u1E6D\u021B\u0163\u1E71\u1E6F\u0167\u01AD\u0288\u2C66\uA787]/g
    },
    {'base': 'tz', 'letters': /[\uA729]/g},
    {
        'base': 'u',
        'letters': /[\u0075\u24E4\uFF55\u00F9\u00FA\u00FB\u0169\u1E79\u016B\u1E7B\u016D\u00FC\u01DC\u01D8\u01D6\u01DA\u1EE7\u016F\u0171\u01D4\u0215\u0217\u01B0\u1EEB\u1EE9\u1EEF\u1EED\u1EF1\u1EE5\u1E73\u0173\u1E77\u1E75\u0289]/g
    },
    {'base': 'v', 'letters': /[\u0076\u24E5\uFF56\u1E7D\u1E7F\u028B\uA75F\u028C]/g},
    {'base': 'vy', 'letters': /[\uA761]/g},
    {'base': 'w', 'letters': /[\u0077\u24E6\uFF57\u1E81\u1E83\u0175\u1E87\u1E85\u1E98\u1E89\u2C73]/g},
    {'base': 'x', 'letters': /[\u0078\u24E7\uFF58\u1E8B\u1E8D]/g},
    {
        'base': 'y',
        'letters': /[\u0079\u24E8\uFF59\u1EF3\u00FD\u0177\u1EF9\u0233\u1E8F\u00FF\u1EF7\u1E99\u1EF5\u01B4\u024F\u1EFF]/g
    },
    {'base': 'z', 'letters': /[\u007A\u24E9\uFF5A\u017A\u1E91\u017C\u017E\u1E93\u1E95\u01B6\u0225\u0240\u2C6C\uA763]/g}
];

function stripAccents(string) {
    var changes = stripAccentsMap;
    for (var i = 0; i < changes.length; i++) {
        string = string.replace(changes[i].letters, changes[i].base);
    }
    return string;
}

function getLoader() {
    let loading = '';
    loading += '<div class="loader">';
    loading += '<div class="text-info text-center" style="font-size: 12px">' + translate("Chargement en cours") + '</div>';
    loading += '<div class="progress progress-striped active" style="height: 7px;">';
    loading += '<div class="progress-bar" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%">';
    loading += '</div>';
    loading += '</div>';
    loading += '</div>';

    return loading;
}

function ucfirst(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}

function isEmail(email) {
    var pattern = new RegExp(/^((([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*)|((\x22)((((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(([\x01-\x08\x0b\x0c\x0e-\x1f\x7f]|\x21|[\x23-\x5b]|[\x5d-\x7e]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(\\([\x01-\x09\x0b\x0c\x0d-\x7f]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))))*(((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(\x22)))@((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?$/i);
    return pattern.test(email);
}


function isISOdate(input, pattern) {
    if (!pattern) pattern = /(^\d{4}-\d{1,2}-\d{1,2}$)/g;
    return (input.match(pattern)) ? true : false;
}

function isValidDate(input, separator) {
    if (!separator) separator = '-';
    var parts = input.split(separator);
    var y = parts[0], m = parts[1], d = parts[2];
    var date = new Date(input);
    return (date == 'Invalid Date' || m != (date.getMonth() + 1)) ? false : true;
}

function dateIsBetween(input, min, max) {
    if (!min || !max) return true;
    var date = new Date(input);
    var min = new Date(min);
    var max = new Date(max);
    return (date >= min && date <= max) ? true : false;
}

function nl2br(str, is_xhtml) {
    var breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '<br />' : '<br>';
    return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + breakTag + '$2');
}

function isPositiveInteger(s) {
    return !!s.match(/^[0-9]+$/);
}

function filterList(input, elements) {
    var query = stripAccents($(input).val());

    if (query.length) {
        $(elements).css('display', 'none');
        $(elements).next('br').css('display', 'none');
        $(elements).filter(function (index) {
            var value = stripAccents($(this).text());
            var regex = new RegExp(query, 'gi');
            if (value.match(regex)) {
                $(this).next('br').css('display', '');
                return true;
            } else {
                return false;
            }
        }).css('display', '');
    } else {
        $(elements).css('display', '');
        $(elements).next('br').css('display', '');
    }
}

function scrollTo(target, container) {
    window.location.hash = target;
}

function htmlEntities(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/**
 * create a permalink from a string
 * @param str
 * @returns string
 */
function permalink(str) {
    str = str.toLowerCase();
    var from = "àáäâèéëêìíïîòóöôùúüûñç·_,:;";
    var to = "aaaaeeeeiiiioooouuuunc-----";
    for (var i = 0, l = from.length; i < l; i++) {
        str = str.replace(new RegExp(from.charAt(i), 'g'), to.charAt(i));
    }
    str = str.replace(/\s/g, '-');
    str = str.replace(/[^a-zA-Z0-9\-]/g, '');
    str = str.toLowerCase();
    str = str.replace(/-+/g, '-');
    str = str.replace(/(^-)|(-$)/g, '');
    return str;
}

function message(text, type) {
    $("#flash-messages").html(getMessageHtml(text, type));
    setTimeout(function () {
        $("#flash-messages").find(".alert").alert("close");
    }, 10000);
}

function getMessageHtml(text, type) {
    return '<div class="alert '
        + type
        + '"><button type="button" class="close" data-dismiss="alert">&times;</button>'
        + text + '</div>';
}

// check if multiling. input languages have been set
function validMultilangInput(id, mce) {
    var langs = [];

    if (mce) {
        $('#' + id).next('div').find('li a').each(function () {
            langs.push($(this).attr('val'));
        });
        for (i in langs) {
            if (!$modal_form.find('textarea[name="' + id + '[' + langs[i] + ']"]').val() && (!tinyMCE.activeEditor.getContent() || $('#' + id + '-element').find('button[data-toggle="dropdown"]').val() != langs[i])) {
                return false;
            }
        }
    } else {
        $('#' + id).next('span').find('li a').each(function () {
            langs.push($(this).attr('val'));
        });
        for (i in langs) {
            if (!$modal_form.find('input[name="' + id + '[' + langs[i] + ']"]').val()) {
                return false;
            }
        }
    }

    return true;
}

/**
 * activate tooltip on each element with data-toggle="tooltip"
 * @param params
 */
function activateTooltips(params = {}) {
    //params = params || {};
    params.container = params.container || 'body';
    params.placement = params.placement || 'bottom';
    params.html = params.html || true;
    params.show = params.show || 200;
    params.hide = params.hide || 100;

    $("[data-toggle~='tooltip']").tooltip({
        'container': params.container,
        'placement': params.placement,
        'html': params.html,
        'delay': {'show': params.show, 'hide': params.hide}
    });
}

/**
 * activate designated tooltip
 * @param $target target element jquery selector
 */
function activateTooltip($target) {
    $target.tooltip({
        delay: {'show': 500},
        html: true,
        placement: 'bottom'
    });
}

function addToggleButton(datatable, target) {
    var toggle_button = '';
    toggle_button += '<button class="dt-toggle-button btn btn-default btn-sm pull-right" ';
    toggle_button += 'style="margin-left: 2px" ';
    toggle_button += 'title="' + translate('Afficher / Masquer') + '">';
    toggle_button += '<span class="glyphicon glyphicon-list-alt"></span>';
    toggle_button += '</button>';
    $(target).prepend(toggle_button);

    var content = '<div class="dt-columns">';
    $(datatable).DataTable().columns().every(function () {
        var th = this.header();
        var i = this.index();
        var label = ($(th).data('name')) ? $(th).data('name') : $(th).html();
        var checked = (this.visible()) ? 'checked' : '';
        content += '<div><input id="col-' + i + '" name="col-' + i + '" type="checkbox" value="' + i + '" ' + checked + ' /> <label for="col-' + i + '">' + label + '</label></div>';
    });
    content += '</div>';

    $('.dt-toggle-button').popover({
        'container': '#container',
        'placement': 'bottom',
        'html': true,
        'content': content
    });

    $('body').on('change', '.dt-columns :checkbox', function () {
        var column = $(datatable).DataTable().column($(this).val());
        column.visible($(this).is(':checked'));
    });

    $('.dt-toggle-button').popover().on('shown.bs.popover', function () {
        $(datatable).DataTable().columns().every(function () {
            $('#col-' + this.index()).prop('checked', this.visible());
        });
    });
}

function applyCollapse(object) {
    // alert($(object).attr('id') + ' : collapsable');
    var openTooltip = translate('Déplier');
    var closeTooltip = translate('Replier');
    var style = ($(object).find('.panel-body:first').hasClass('in')) ? 'glyphicon-chevron-up' : 'glyphicon-chevron-down';
    var tooltip = ($(object).find('.panel-body:first').hasClass('in')) ? closeTooltip : openTooltip;
    var button = '<div class="collapseButton" data-toggle="tooltip" title="' + tooltip + '"><span class="glyphicon ' + style + '"></span></div>';
    $(object).find('.panel-heading:first .panel-title').append(button);
    if (!$(object).find('.panel-body:first').hasClass('in')) {
        $(object).find('.panel-body').css('display', 'none');
    }

    $('.collapseButton', object).tooltip();
    $('.panel-heading:first', object).click(function () {
        $(this).closest('.panel').find('.panel-body').toggle();
        if ($(this).find('.collapseButton span').hasClass('glyphicon-chevron-down')) {
            $(this).find('.collapseButton span').removeClass('glyphicon-chevron-down');
            $(this).find('.collapseButton span').addClass('glyphicon-chevron-up');

            $(this).find('.collapseButton').tooltip('destroy');
            $(this).find('.collapseButton').attr('title', closeTooltip);
            $(this).find('.collapseButton').tooltip();

        } else {
            $(this).find('.collapseButton span').removeClass('glyphicon-chevron-up');
            $(this).find('.collapseButton span').addClass('glyphicon-chevron-down');

            $(this).find('.collapseButton').tooltip('destroy');
            $(this).find('.collapseButton').attr('title', openTooltip);
            $(this).find('.collapseButton').tooltip();
        }
    });
}

function modalStructureExists() {
    return ($modal_box && $modal_box.length);
}

function createModalStructure(params) {
    // create modal structure from a view script
    $.ajax({
        url: '/partial/modal',
        type: 'POST',
        data: params,
        success: function (modalStructure) {
            $('body').append(modalStructure);
            $modal_box = $('#modal-box');
            $modal_body = $modal_box.find('.modal-body');
            $modal_footer = $modal_box.find('.modal-footer');
            $modal_button = $('#submit-modal');
        }
    });
}

function openModal(url, title, params, source) {

    // set css params
    if (params) {
        for (let key in params) {
            if ($('body').css(key) !== 'undefined') {
                $modal_box.find('.modal-dialog').css(key, params[key]);
            }
        }
    }

    // if modal does not exist, return false
    if (!modalStructureExists()) {
        return false;
    }

    params.hidesubmit ? $modal_button.hide() : $modal_button.show();

    // run callback method (if there is one)
    if (params['callback']) {
        $modal_button.off('click.callback');
        $modal_button.on('click.callback', {callback: params['callback']}, function (e) {
            var callback = e.data.callback;
            if (jQuery.isFunction(window[callback])) {
                window[callback](source.target);
            }
        });
    } else {
        $modal_button.on('click', function (e) {
            // submit form if necessary
            if ($modal_form.length && $modal_form.data('submission') != false) {
                $modal_form.submit();
            }
            $modal_box.modal('hide');
        });
    }

    // init modal
    $modal_box.find('.modal-title').html(title);
    $modal_box.draggable({handle: ".modal-header"});
    if (url) {
        // if ajax, destroy TinyMCE editors before refreshing content
        $modal_box.find('textarea').each(function () {
            if (typeof (tinyMCE) != "undefined") {
                tinyMCE.execCommand('mceRemoveEditor', false, $(this).attr('id'));
            }
        });
        $modal_body.html(getLoader());
    }
    $modal_box.modal();


    // load content from remote url (ajax)
    if (url) {
        let oUrl = $.url(url);
        let urlParams = oUrl.param();
        urlParams['ajax'] = true;

        let displayContactsRequest = ajaxRequest(oUrl.attr('path'), urlParams );
        displayContactsRequest.done(function(content){
            $modal_body.html(content);
            $modal_form = $modal_box.find('form');
        });

    } else if (params['content']) {
        $modal_body.html(params['content']);
    } else if (params['source']) {
        $(params['source']).appendTo('#modal-box .modal-body');
    }

    // run init method (if there is one)
    if (params['init']) {
        if (jQuery.isFunction(window[params['init']])) {
            window[params['init']](source.target);
        }
    }

    // run onclocse method (if there is one)
    if (params['onclose'] && jQuery.isFunction(window[params['onclose']])) {
        $modal_box.on('hidden.bs.modal', function () {
            window[params['onclose']](source.target);
        });
    }

    $('#myModal').on('hidden.bs.modal', function () {
        // do something…
    })
}

function resizeModal(width, height) {
    if (!width) {
        width = '560px';
    }
    if (!height) {
        height = '400px';
    }

    $modal_box.find('.modal').css('width', width);
    $modal_box.find('.modal').css('margin-left', function () {
        return -($(this).width() / 2);
    });
    $modal_body.css({'max-height': height});
}

function resetModalSize() {
    var default_width = '560px';
    var default_height = '400px';
    var default_ml = '-' + (default_width / 2) + 'px';

    $modal_box.find('.modal').css({
        'width': default_width,
        'margin': '-250px 0 0 ' + default_ml
    });

    $modal_body.css({
        'max-height': default_height
    });
}

/**
 * Envoie une chaine de caractères correspondant à la date exprimée selon une locale.
 * Les arguments locales et options permettent de définir le langage utilisé pour
 * les conventions de format et permettent de personnaliser le comportement de la fonction
 * @param date
 * @param oLocale
 * @param options
 * @returns {string}
 */

function getLocaleDate(date , oLocale = {language: 'en', country: 'br'}, options = {year: 'numeric', month: 'long', day: 'numeric'}) {

    let parseDate = Date.parse(date);

    if (isNaN(parseDate)) {
        return 'Invalid Date';
    }

    let lang = oLocale.language;
    let count = oLocale.country;

    let locale = lang + '-' + count.toUpperCase();

    return new Date(parseDate).toLocaleDateString(locale, options);
}

/**
 *
 * @param body
 * @param tagName
 * @param date
 * @returns {*}
 */
function updateDeadlineTag(body, tagName, date, locale = 'en') {
    let content = body.getContent();
    let search =  new RegExp('<span class="' + tagName + '">(.*?)<\/span>');
    let replace = '<span class="' + tagName + '">' + getLocaleDate(date, {
        language: locale,
        country: locale
    }) + '</span>';

    content = content.replace(search, replace);
    body.setContent(content);
    return body;
}

/**
 * get content form tinymce object.
 * @returns {string}
 * @param {object} name
 */
function getObjectNameFromTinyMce(name) {
    let body = {};
    if (tinymce) {
        body = tinymce.get(name);
    }
    return body;
}

/**
 *
 * @param url
 * @param jData{*}
 * @param type
 * @param dataType
 * @returns {*}
 */
function ajaxRequest(url, jData, type = 'POST', dataType = null) {
    let params = {
        url: url,
        type: type,
        data: jData
    };

    if (dataType) {
        params.datatype = dataType;

    }
    return $.ajax(params);
}

/**
 *get multi js scripts
 * @param sArr
 * @returns {*}
 */

function getMultiScripts(sArr) {
    let _sArr = $.map(sArr, function (scr) {
        return $.getScript((scr.path || "") + scr.script);
    });

    _sArr.push($.Deferred(function (deferred) {
        $(deferred.resolve);
    }));

    return $.when.apply($, _sArr);
}

function in_array(needle, haystack, strict = false) {
    let found = 0;
    for (let i = 0, len = haystack.length; i < len; i++) {
        let condition = (!strict) ? (haystack[i] == needle) : (haystack[i] === needle);
        if (condition) return i;
        found++;
    }
    return -1;
}

/**
 * Clear error messages
 * @param selector
 */
function clearErrors(selector = '.errors') {
    if($(selector).length){
        if ($(selector)) {
            $(selector).empty();
        }
    }
}


function isValidHttpUrl(string) {
    let url;

    try {
        url = new URL(string);
    } catch (_) {
        return false;
    }

    return url.protocol === "http:" || url.protocol === "https:";
}

// jQuery sortable table bug fix
let fixHelperSortable = function (e, ui) {
    ui.children().each(function () {
        $(this).width($(this).width());
    });
    return ui;
};



