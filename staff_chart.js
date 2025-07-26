document.addEventListener("DOMContentLoaded", () => {
    if (!chartData || chartData.length === 0) return;

    const ctx = document.getElementById('staffChart').getContext('2d');

    const branches = [...new Set(chartData.map(item => item.branch_office))];
    // const labels = [...new Set(chartData.map(item => `${item.year}/${item.month}`))].sort();
    const labels = [...new Set(chartData.map(item => `${item.year}/${item.month}`))]
    .sort((a, b) => {
        const [yearA, monthA] = a.split('/').map(Number);
        const [yearB, monthB] = b.split('/').map(Number);
        return yearA !== yearB ? yearA - yearB : monthA - monthB;
    });

   


    const datasets = branches.map(branch => {
        const data = labels.map(label => {
            const [year, month] = label.split('/');
            const found = chartData.find(item => item.branch_office === branch && item.year == year && item.month == month);
            return found ? found.staff_count : 0;
        });
        return {
            label: branch,
            data,
            borderColor: randomColor(),
            backgroundColor: 'transparent',
            tension: 0.2,
            pointRadius: 3,
            pointHoverRadius: 6
        };
    });

    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false, // ← 高さ優先に変更
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        font: {
                            size: 16 // 凡例フォントサイズ
                        }
                    }
                },
                title: {
                    display: true,
                    text: 'Staff Chart',
                    font: {
                        size: 20 // タイトルフォントサイズ
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Staff Count',
                        font: {
                            size: 18 // Y軸タイトルフォント
                        }
                    },
                    ticks: {
                        font: {
                            size: 16 // Y軸目盛フォント
                        }
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Year/Month',
                        font: {
                            size: 18 // X軸タイトルフォント
                        }
                    },
                    ticks: {
                        font: {
                            size: 16 // X軸目盛フォント
                        }
                    }
                }
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            }
        },
    });

    function randomColor() {
        const r = Math.floor(Math.random() * 156 + 100);
        const g = Math.floor(Math.random() * 156 + 100);
        const b = Math.floor(Math.random() * 156 + 100);
        return `rgb(${r},${g},${b})`;
    }
});
