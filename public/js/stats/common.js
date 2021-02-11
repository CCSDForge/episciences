function createChart(context, type, data, options) {
    return new Chart(context, {
        data: data,
        type: type,
        options: options
    });
}