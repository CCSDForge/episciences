/**
 * Dashboard Paper Search Module
 * Handles paper search functionality with improved error handling and user feedback
 */
class DashboardPaperSearch {
    constructor(container) {
        this.container = container;
        this.identifier = container.querySelector('[data-role="paper-identifier"]');
        this.submitBtn = container.querySelector('[data-role="submit-btn"]');
        this.suffix = container.dataset.suffix || '';
        this.from = container.dataset.from || '';
        
        if (!this.identifier || !this.submitBtn) {
            console.error('Dashboard paper search: Required elements not found in container', container);
            return;
        }

        this.init();
    }

    init() {
        this.submitBtn.addEventListener('click', (e) => {
            e.preventDefault();
            this.goToArticle();
        });

        this.identifier.addEventListener('keyup', (e) => {
            if (e.key === 'Enter' || e.keyCode === 13) {
                this.goToArticle();
            }
        });
    }

    goToArticle() {
        const idValue = this.identifier.value.trim();
        
        if (!idValue) {
            alert(translate("Veuillez indiquer l'identifiant du document."));
            return;
        }

        if (isNaN(idValue) || idValue <= 0) {
            alert(translate("La valeur saisie n'est pas correcte, l'identifiant doit être un nombre positif."));
            return;
        }

        this.setLoadingState(true);

        const formData = new FormData();
        formData.append('id', idValue);
        formData.append('from', this.from);

        fetch(JS_PREFIX_URL + 'paper/ajaxgetlastpaperid', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.text();
        })
        .then(response => {
            try {
                const jsonParsed = JSON.parse(response);
                const lastDocid = parseInt(jsonParsed.docid);
                
                if (jsonParsed.exception) {
                    console.error('Server exception:', jsonParsed.exception);
                    alert(translate("Une erreur interne s'est produite, veuillez recommencer."));
                    return;
                }
                
                if (lastDocid && lastDocid > 0) {
                    window.location.href = JS_PREFIX_URL + jsonParsed.controller + '/view?id=' + lastDocid;
                } else if (jsonParsed.error) {
                    alert(jsonParsed.error);
                } else {
                    // No explicit error but docid is 0 - paper doesn't exist
                    alert(translate("L'article avec l'identifiant : ") + idValue + translate(" n'existe pas ou n'est pas accessible."));
                }
            } catch (parseError) {
                console.error('JSON parse error:', parseError, 'Response:', response);
                alert(translate("Erreur lors du traitement de la réponse du serveur."));
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            alert(translate("Erreur de connexion. Veuillez vérifier votre connexion réseau et réessayer."));
        })
        .finally(() => {
            this.setLoadingState(false);
        });
    }

    setLoadingState(isLoading) {
        if (isLoading) {
            this.submitBtn.disabled = true;
            this.originalBtnContent = this.submitBtn.innerHTML;
            this.submitBtn.innerHTML = '<span class="glyphicon glyphicon-refresh glyphicon-spin"></span>';
        } else {
            this.submitBtn.disabled = false;
            this.submitBtn.innerHTML = this.originalBtnContent;
        }
    }
}

// Initialize all dashboard paper search components when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    const searchContainers = document.querySelectorAll('[data-component="dashboard-paper-search"]');
    searchContainers.forEach(container => {
        new DashboardPaperSearch(container);
    });
});