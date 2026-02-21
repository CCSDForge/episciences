var form = new Object();

form.init = function (formElement) {
    this.formElement = formElement;
};

form.validationRules = function (params) {
    this.rules = params;
};

form.validate = function () {
    this.errors = [];

    // Vérifie l'existence de règles de validation
    if (!this.rules) {
        console.log(translate("Aucune règle de validation n'a été définie."));
        return true;
    }

    // Vérifie la présence des champs obligatoires
    if (this.rules.required) {
        var required = this.rules.required;

        for (var i in required) {
            var name = required[i].name;
            var element = '#' + name;

            // Teste si l'élément existe
            if (!$(element).length) {
                console.log("L'élément " + name + " n'existe pas.");
                continue;
            }

            // var tag = $(element).get(0).tagName.toLowerCase();
            var message = required[i].message;
            var validated = false;

            // Element TinyMCE
            if ($(element).is('textarea') && tinyMCE.editors[name]) {
                if (tinyMCE.editors[name].getContent()) {
                    validated = true;
                    continue;
                }
            }
            // Autres types d'élément
            else if ($(element).val()) {
                validated = true;
                continue;
            }

            // En cas d'erreur
            if (!validated) {
                this.errors.push(translate(message));
            }
        }
    }

    // Renvoie le résultat de la validation
    return !this.errors.length;
};

form.ajaxSubmit = function () {
    // Teste l'existence du formulaire
    if (!$(this.formElement).length) {
        console.log(translate("Le formulaire n'existe pas."));
        return false;
    }

    $(this.formElement).find('button[type="submit"]').click();
};

form.displayErrors = function () {
    alert(
        translate('Le formulaire est invalide') +
            ' : \r\n\r\n - ' +
            this.errors.join('\r\n - ')
    );
};
