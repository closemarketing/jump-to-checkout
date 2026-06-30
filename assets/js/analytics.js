(function() {
	'use strict';

	let timelineChart = null;
	let topLinksChart = null;
	let conversionChart = null;

	document.addEventListener('DOMContentLoaded', function() {
		if (typeof Chart === 'undefined') {
			console.error('Chart.js not loaded');
			return;
		}

		loadAnalyticsData();
	});

	/**
	 * Load analytics data
	 */
	function loadAnalyticsData() {
		const data = {
			action: 'jptc_get_analytics_data',
			nonce: jptcAnalytics.nonce
		};

		fetch(jptcAnalytics.ajax_url + '?' + new URLSearchParams(data))
			.then(function(response) {
				return response.json();
			})
			.then(function(response) {
				if (response.success) {
					updateStats(response.data.stats);
					renderTimelineChart(response.data.timeline);
					renderTopLinksChart(response.data.top_links);
				} else {
					console.error('Error loading analytics:', response.data);
				}
			})
			.catch(function(error) {
				console.error('Error:', error);
			});
	}

	/**
	 * Update stats cards
	 */
	function updateStats(stats) {
		document.getElementById('jptc-stat-total-links').textContent = stats.total_links || 0;
		document.getElementById('jptc-stat-active-links').textContent = stats.active_links || 0;
		document.getElementById('jptc-stat-total-visits').textContent = stats.total_visits || 0;
		document.getElementById('jptc-stat-total-conversions').textContent = stats.total_conversions || 0;
		document.getElementById('jptc-stat-avg-conversion').textContent = (stats.avg_conversion_rate || 0) + '%';
	}

	/**
	 * Render timeline chart
	 */
	function renderTimelineChart(timelineData) {
		const ctx = document.getElementById('jptc-chart-timeline');
		if (!ctx) {
			return;
		}

		const labels = Object.keys(timelineData);
		const visits = labels.map(function(date) {
			return timelineData[date].visits || 0;
		});
		const conversions = labels.map(function(date) {
			return timelineData[date].conversions || 0;
		});

		if (timelineChart) {
			timelineChart.destroy();
		}

		timelineChart = new Chart(ctx, {
			type: 'line',
			data: {
				labels: labels,
				datasets: [
					{
						label: jptcAnalytics.i18n.visits,
						data: visits,
						borderColor: 'rgb(75, 192, 192)',
						backgroundColor: 'rgba(75, 192, 192, 0.2)',
						tension: 0.1
					},
					{
						label: jptcAnalytics.i18n.conversions,
						data: conversions,
						borderColor: 'rgb(255, 99, 132)',
						backgroundColor: 'rgba(255, 99, 132, 0.2)',
						tension: 0.1
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: true,
				scales: {
					y: {
						beginAtZero: true
					}
				}
			}
		});
	}

	/**
	 * Render top links chart
	 */
	function renderTopLinksChart(topLinks) {
		const ctx = document.getElementById('jptc-chart-top-links');
		if (!ctx) {
			return;
		}

		const labels = topLinks.map(function(link) {
			return link.name;
		});
		const conversions = topLinks.map(function(link) {
			return link.conversions;
		});

		if (topLinksChart) {
			topLinksChart.destroy();
		}

		topLinksChart = new Chart(ctx, {
			type: 'bar',
			data: {
				labels: labels,
				datasets: [
					{
						label: jptcAnalytics.i18n.conversions,
						data: conversions,
						backgroundColor: 'rgba(54, 162, 235, 0.5)',
						borderColor: 'rgba(54, 162, 235, 1)',
						borderWidth: 1
					}
				]
			},
			options: {
				responsive: true,
				maintainAspectRatio: true,
				scales: {
					y: {
						beginAtZero: true
					}
				}
			}
		});
	}
})();
