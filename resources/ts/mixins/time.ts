import moment from "moment"

import { defineComponent } from "vue"

export default defineComponent({
  methods: {
    full_human_date(time: string | number, local: boolean = true): string {
      let date = moment.utc(time)
      if (local) {
        date = date.local()
      }
      if (!date.isValid()) {
        return ""
      }
      return date.format("dddd, MMMM D, Y")
    },
    short_human_date(time: string | number): string {
      const date = moment.utc(time)
      /*if (local) {
        date = date.local()
      }*/
      if (!date.isValid()) {
        return ""
      }
      return date.format("MMM D, Y")
    },
    day(time: string | number): string {
      const date = moment.utc(time)
      /*if (local) {
        date = date.local()
      }*/
      if (!date.isValid()) {
        return ""
      }
      return date.format("dddd")
    },
    full_date_time(time: string | number, local: boolean = true): string {
      let date = moment.utc(time)
      if (local) {
        date = date.local()
      }
      if (!date.isValid()) {
        return ""
      }
      return date.format("dddd, MMMM D, Y h:mm a")
    },
    short_date_time(time: string | number, local: boolean = true): string {
      let date = moment.utc(time)
      if (local) {
        date = date.local()
      }
      if (!date.isValid()) {
        return ""
      }
      return date.format("MMM D, Y h:mm a")
    },
    time(time: string | number, local: boolean = true): string {
      let date = moment.utc(time)
      if (local) {
        date = date.local()
      }
      if (!date.isValid()) {
        return ""
      }
      return date.format("h:mm a")
    },
    time_ago(time: string | number): string {
      return moment.min(moment.utc(time).local(), moment()).fromNow()
    },
    calendar(time: string | number): string {
      const date = moment.utc(time).local()
      if (!date.isValid()) {
        return "Never"
      }
      return date.calendar(null, {
        sameDay: "[Today] [@] h:mma",
        nextDay: "[Tomorrow] [@] h:mma",
        nextWeek: "dddd [@] h:mma",
        lastDay: "[Yesterday] [@] h:mma",
        lastWeek: "[Last] dddd [@] h:mma",
        sameElse: "MMM D, Y h:mma"
      })
    },
    formatTime(value: string | number, format: string): string {
      const date = moment.utc(value)
      if (!date.isValid()) {
        return ""
      }
      return date.format(format)
    },
    short_date_time_12(time: string | number, local: boolean = true): string {
      let date = moment.utc(time)
      if (local) {
        date = date.local()
      }
      if (!date.isValid()) {
        return ""
      }
      return date.format("MM/DD/YYYY h:mm a")
    },
    short_date(time: string | number): string {
      const date = moment.utc(time)
      /*if (local) {
        date = date.local()
      }*/
      if (!date.isValid()) {
        return ""
      }
      return date.format("MM/DD/YYYY")
    },
    month_year(time: string | number): string {
      const date = moment.utc(time)
      /*if (local) {
        date = date.local()
      }*/
      if (!date.isValid()) {
        return ""
      }
      return date.format("MM/YYYY")
    }
  }
})
