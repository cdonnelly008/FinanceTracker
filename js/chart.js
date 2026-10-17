var dashboardChartInstance = null;
var reportsExpenseChartInstance = null;
var reportsComparisonChartInstance = null;

// quick theme check so the chart colours match the current mode
function isDarkMode() {
    return document.documentElement.classList.contains('theme-dark');
}

// builds the dashboard spending chart
function loadDashboardChart() {
    var trendCanvas = document.getElementById('spendingTrendChart');

    if (!trendCanvas || typeof Chart === 'undefined') {
        return;
    }

    var labels = [];
    var values = [];
    var currencyCode = trendCanvas.getAttribute('data-currency') || 'GBP';

    try {
        labels = JSON.parse(trendCanvas.getAttribute('data-labels')) || [];
        values = JSON.parse(trendCanvas.getAttribute('data-values')) || [];
    } catch (e) {
        return;
    }

    if (dashboardChartInstance) {
        dashboardChartInstance.destroy();
        dashboardChartInstance = null;
    }

    var ctx = trendCanvas.getContext('2d');

    // uses a different gradient in light and dark mode
    var barGradient = ctx.createLinearGradient(0, 0, 0, 300);
    if (isDarkMode()) {
        barGradient.addColorStop(0, '#38bdf8');
        barGradient.addColorStop(1, '#0284c7');
    } else {
        barGradient.addColorStop(0, '#22c55e');
        barGradient.addColorStop(1, '#16a34a');
    }
    var hoverColor = isDarkMode() ? '#38bdf8' : '#22c55e';

    dashboardChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Spending',
                data: values,
                backgroundColor: barGradient,
                borderRadius: 10,
                borderSkipped: false,
                hoverBackgroundColor: hoverColor,
                maxBarThickness: 42,
                categoryPercentage: 0.7,
                barPercentage: 0.75
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            layout: {
                padding: {
                    top: 10,
                    right: 12,
                    bottom: 0,
                    left: 8
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                title: {
                    display: false
                },
                tooltip: {
                    backgroundColor: '#0f172a',
                    titleColor: '#f8fafc',
                    bodyColor: '#cbd5e1',
                    borderColor: 'rgba(255,255,255,0.08)',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: false,
                    callbacks: {
                        label: function (context) {
                            return 'Spent: ' + currencyCode + ' ' + Number(context.raw).toFixed(2);
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false,
                        drawBorder: false
                    },
                    border: {
                        display: false
                    },
                    ticks: {
                        color: '#94a3b8',
                        font: {
                            size: 12,
                            weight: '600'
                        }
                    }
                },
                y: {
                    beginAtZero: true,
                    border: {
                        display: false
                    },
                    grid: {
                        color: 'rgba(148, 163, 184, 0.10)',
                        drawBorder: false
                    },
                    ticks: {
                        color: '#94a3b8',
                        padding: 10,
                        callback: function (value) {
                            return currencyCode + ' ' + value;
                        }
                    }
                }
            },
            animation: {
                duration: 900,
                easing: 'easeOutQuart'
            }
        }
    });
}

// builds the two charts on the reports page
function loadReportsCharts() {
    var expenseCanvas = document.getElementById('expenseDoughnutChart');
    var comparisonCanvas = document.getElementById('incomeExpenseChart');

    if (expenseCanvas && typeof Chart !== 'undefined') {
        var labels = [];
        var values = [];
        var expenseCurrency = expenseCanvas.getAttribute('data-currency') || 'GBP';

        try {
            labels = JSON.parse(expenseCanvas.getAttribute('data-labels')) || [];
            values = JSON.parse(expenseCanvas.getAttribute('data-values')) || [];
        } catch (e) {
            labels = [];
            values = [];
        }

        if (reportsExpenseChartInstance) {
            reportsExpenseChartInstance.destroy();
            reportsExpenseChartInstance = null;
        }

        if (labels.length > 0) {
            // keeps light mode more on-brand and lets dark mode be a bit louder
            // same colours as the dots in the list under the chart (.report-cat-dot-*)
            var palette = isDarkMode()
                ? ['#38bdf8', '#f472b6', '#fbbf24', '#34d399', '#818cf8', '#a78bfa', '#fb923c', '#f87171']
                : ['#16a34a', '#3b82f6', '#f59e0b', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316', '#ef4444'];
            var otherColor = isDarkMode() ? '#64748b' : '#94a3b8';
            var doughnutColors = labels.map(function (label, i) {
                return label === 'Other' ? otherColor : palette[i % palette.length];
            });
            reportsExpenseChartInstance = new Chart(expenseCanvas, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: doughnutColors,
                        // a thin gap between slices makes small ones easier to see
                        borderWidth: 2,
                        borderColor: isDarkMode() ? '#161b27' : '#ffffff',
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    layout: {
                        padding: 4
                    },
                    plugins: {
                        title: {
                            display: false
                        },
                        // on desktop the coloured list under the chart already acts as the legend
                        legend: {
                            display: window.matchMedia('(max-width: 900px)').matches,
                            position: 'bottom',
                            labels: {
                                boxWidth: 10,
                                boxHeight: 10,
                                padding: 10,
                                usePointStyle: true,
                                pointStyle: 'circle',
                                font: {
                                    size: 11
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return context.label + ': ' + expenseCurrency + ' ' + Number(context.raw).toFixed(2);
                                }
                            }
                        }
                    }
                }
            });
        }
    }

    if (comparisonCanvas && typeof Chart !== 'undefined') {
        var labels = [];
        var values = [];
        var comparisonCurrency = comparisonCanvas.getAttribute('data-currency') || 'GBP';

        try {
            labels = JSON.parse(comparisonCanvas.getAttribute('data-labels')) || [];
            values = JSON.parse(comparisonCanvas.getAttribute('data-values')) || [];
        } catch (e) {
            labels = [];
            values = [];
        }

        if (reportsComparisonChartInstance) {
            reportsComparisonChartInstance.destroy();
            reportsComparisonChartInstance = null;
        }

        if (values.length > 0) {
            var barColors = isDarkMode()
                ? ['#38bdf8', '#f472b6']
                : ['#16a34a', '#ef4444'];
            reportsComparisonChartInstance = new Chart(comparisonCanvas, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: barColors,
                        borderRadius: 10,
                        borderSkipped: false,
                        maxBarThickness: 72
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 6,
                            right: 6,
                            bottom: 0,
                            left: 0
                        }
                    },
                    plugins: {
                        title: {
                            display: false
                        },
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return comparisonCurrency + ' ' + Number(context.raw).toFixed(2);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#64748b',
                                font: {
                                    size: 12,
                                    weight: '600'
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(148, 163, 184, 0.15)'
                            },
                            ticks: {
                                color: '#64748b',
                                callback: function (value) {
                                    return comparisonCurrency + ' ' + value;
                                }
                            }
                        }
                    }
                }
            });
        }
    }
}

document.addEventListener('DOMContentLoaded', function () {
    loadDashboardChart();
    loadReportsCharts();
});
