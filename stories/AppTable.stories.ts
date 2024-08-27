import type { Meta, StoryObj } from '@storybook/vue3'
import AppTable from '@/components/AppTable.vue'

// More on how to set up stories at: https://storybook.js.org/docs/writing-stories
const meta = {
  title: 'AppTable',
  component: AppTable,
  args: {
    endpoint: '/users',
    headers: [
      { title: 'ID', value: 'id' },
      { title: 'Name', value: 'full_name' },
      { title: 'Email', value: 'email' },
      { title: 'Phone', value: 'phone' },
      { title: 'Website', value: 'website' },
    ]
  },
  argTypes: {
  }
} satisfies Meta<typeof AppTable>

export default meta
type Story = StoryObj<typeof meta>;
/*
 *👇 Render functions are a framework specific feature to allow you control on how the component renders.
 * See https://storybook.js.org/docs/api/csf
 * to learn how to use render functions.
 */
export const Primary: Story = {
  args: {
  },
}
