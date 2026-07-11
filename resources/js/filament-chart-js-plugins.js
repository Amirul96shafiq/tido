const filamentTooltipThemePlugin = {
    id: 'filamentTooltipTheme',

    beforeInit(chart) {
        applyFilamentTooltipTheme(chart)
    },

    beforeUpdate(chart) {
        applyFilamentTooltipTheme(chart)
    },
}

function applyFilamentTooltipTheme(chart) {
    const isDark = document.documentElement.classList.contains('dark')
    const existingTooltip = chart.options.plugins?.tooltip ?? {}
    const rootStyles = getComputedStyle(document.documentElement)
    const slate700 = rootStyles.getPropertyValue('--color-slate-700').trim() || '#334155'

    const theme = isDark
        ? {
              backgroundColor: slate700,
              titleColor: '#ffffff',
              bodyColor: '#ffffff',
              borderColor: 'transparent',
              borderWidth: 0,
          }
        : {
              backgroundColor: '#ffffff',
              titleColor: '#26323d',
              bodyColor: '#26323d',
              borderColor: 'rgba(0, 0, 0, 0.05)',
              borderWidth: 1,
          }

    const preservedTooltipOptions = {}

    for (const key of ['enabled', 'callbacks', 'external', 'filter', 'itemSort', 'intersect', 'mode']) {
        if (existingTooltip[key] !== undefined) {
            preservedTooltipOptions[key] = existingTooltip[key]
        }
    }

    chart.options.plugins ??= {}
    chart.options.plugins.tooltip = {
        cornerRadius: 4,
        padding: 9,
        titleFont: {
            size: 14,
            weight: '400',
            family: chart.options.font?.family,
        },
        bodyFont: {
            size: 14,
            weight: '400',
            family: chart.options.font?.family,
        },
        displayColors: true,
        boxWidth: 8,
        boxHeight: 8,
        usePointStyle: true,
        ...theme,
        ...preservedTooltipOptions,
    }
}

window.filamentChartJsGlobalPlugins ??= []
window.filamentChartJsGlobalPlugins.push(filamentTooltipThemePlugin)
