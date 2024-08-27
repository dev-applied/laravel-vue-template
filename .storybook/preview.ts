import { setup } from "@storybook/vue3";
import type { Preview } from "@storybook/vue3";
import vuetify from "../resources/ts/plugins/vuetify"
import { usePlugins } from "../resources/ts/plugins"
import { withVuetifyTheme } from './withVuetifyTheme.decorator';
import { createPinia } from "pinia"
import { loadLayouts } from "../resources/ts/layouts"
import { INITIAL_VIEWPORTS, DEFAULT_VIEWPORT } from '@storybook/addon-viewport';


setup(app => {
  app.use(createPinia())
  app.use(vuetify)
  loadLayouts(app)
  usePlugins(app)
})

export const globalTypes = {
  theme: {
    name: 'Theme',
    description: 'Global theme for components',
    toolbar: {
      icon: 'paintbrush',
      // Array of plain string values or MenuItem shape
      items: [
        { value: 'light', title: 'Light', left: '🌞' },
        { value: 'dark', title: 'Dark', left: '🌛' },
      ],
      // Change title based on selected value
      dynamicTitle: true,
    },
  },
};

const preview: Preview = {
  parameters: {
    docs: {
      story: {
        inline: true
      }
    },
  },
  tags: ['autodocs'],
};

export const decorators = [withVuetifyTheme];

export default preview;
