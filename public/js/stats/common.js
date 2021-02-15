function createChart(context, data, type = null, title = '') {
    let options = {
        plugins: {
            title: {
                display: true,
                text: title
            }
        },
    };

    if (type !== 'pie') {
        let scalesOptions = {
            scales: {
                x: {
                    gridLines: {
                        display: false
                    }
                },
                y: {
                    gridLines: {
                        display: false
                    }
                }
            }
        }
        Object.assign(options, scalesOptions);
    }

    if (type === 'barH') { // Graphique à barres horizontales
        options['indexAxis'] = 'y';
        type = 'bar';
    }

    if (type === null) {
        type = 'bar'
    }

    return new Chart(context, {
        data: data,
        type: type,
        options: options
    });
}