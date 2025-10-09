<script>
    $(document).ready(function() {
        var ctx = document.getElementById('stockChart').getContext('2d');

        var gradientIn = ctx.createLinearGradient(0, 0, 0, 400);
        gradientIn.addColorStop(0, 'rgba(75, 192, 192, 0.5)');
        gradientIn.addColorStop(1, 'rgba(75, 192, 192, 0)');

        var gradientOut = ctx.createLinearGradient(0, 0, 0, 400);
        gradientOut.addColorStop(0, 'rgba(255, 99, 132, 0.5)');
        gradientOut.addColorStop(1, 'rgba(255, 99, 132, 0)');

        var stockChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: {!! json_encode($stockChartData['labels'] ?? []) !!},
                datasets: [{
                    label: 'Stok Masuk',
                    data: {!! json_encode($stockChartData['stock_in'] ?? []) !!},
                    backgroundColor: gradientIn,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 2,
                    borderRadius: 5,
                    tension: 0.4
                }, {
                    label: 'Stok Keluar',
                    data: {!! json_encode($stockChartData['stock_out'] ?? []) !!},
                    backgroundColor: gradientOut,
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 2,
                    borderRadius: 5,
                    tension: 0.4
                }]
            },
            options: {
                animation: {
                    duration: 1000,
                    easing: 'easeInOutQuad'
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(200, 200, 200, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.7)',
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 12
                        },
                        callbacks: {
                            label: function(context) {
                                return `${context.dataset.label}: ${context.formattedValue}`;
                            }
                        }
                    }
                }
            }
        });
    });
</script>
