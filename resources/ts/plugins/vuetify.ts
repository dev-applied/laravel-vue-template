import Vue from 'vue'
import Vuetify from 'vuetify'

Vue.use(Vuetify)

export default new Vuetify({
    theme: {
        themes: {
            light: {
                primary: '#502075',
                secondary: '#ffc941',
                tertiary: '#495057',
                accent: '#82B1FF',
                error: '#f55a4e',
                info: '#00d3ee',
                success: '#5cb860',
                warning: '#ffa21a',
                application: '#EFEFEF'
            }
        }
    },
    iconfont: 'mdi',
    options: { customProperties: true, variations: false },
})
