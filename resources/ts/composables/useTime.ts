import moment from "moment"

import { defineComponent } from "vue"

type TimeValue =  string | number | undefined | null

export default defineComponent({
  methods: {
    formatTime(value: TimeValue, format: string, local: boolean = true): string {
      if (!value) {
        return ""
      }
      let date = moment.utc(value)
      if (!date.isValid()) {
        return ""
      }
      if (local) {
        date = date.local()
      }
      return date.format(format)
    },
    full_human_date(time: TimeValue, local: boolean = true): string {
      return this.formatTime(time, "dddd, MMMM D, Y", local)
    },
    short_human_date(time: TimeValue, local: boolean = true): string {
      return this.formatTime(time, "MMM D, Y", local)
    },
    day(time: TimeValue, local: boolean = true): string {
      return this.formatTime(time,"dddd", local)
    },
    full_date_time(time: TimeValue, local: boolean = true): string {
      return this.formatTime(time, "dddd, MMMM D, Y h:mm a", local)
    },
    short_date_time(time: TimeValue, local: boolean = true): string {
      return this.formatTime(time, "MMM D, Y h:mm a", local)
    },
    time(time: TimeValue, local: boolean = true): string {
      return this.formatTime(time,"h:mma", local)
    },
    short_date_time_12(time: TimeValue, local: boolean = false): string {
      return this.formatTime(time, "MM/DD/YYYY h:mm a", local)
    },
    short_date(time: TimeValue, local: boolean = true): string {
      return this.formatTime(time,"MM/DD/YYYY", local)
    },
    month_year(time: TimeValue, local: boolean = true): string {
      return this.formatTime(time,"MM/YYYY", local)
    },
    time_ago(time: TimeValue, local: boolean = true): string {
      if (!time) {
        return ""
      }
      let date = moment.utc(time)
      if (!date.isValid()) {
        return ""
      }
      if (local) {
        date = date.local()
      }
      return moment.min(date.endOf('day'), moment()).fromNow()
    },
    time_future(time: TimeValue, local: boolean = true): string {
      if (!time) {
        return ""
      }
      let date = moment.utc(time)
      if (!date.isValid()) {
        return ""
      }
      if (local) {
        date = date.local()
      }
      const start = date.clone().startOf('day')
      const end = date.clone().add(1, 'second').startOf('day')

      if (start.isSame(end)) {
        return "Today"
      }
      return moment.max(start).to(end)
    },
    time_calculate(time: TimeValue, local: boolean = true): string {
      if (!time) {
        return ""
      }
      let date = moment.utc(time)
      if (!date.isValid()) {
        return ""
      }
      if (local) {
        date = date.local()
      }
      if(date.isAfter()) {
        return this.time_future(time, local)
      } else {
        return this.time_ago(time, local)
      }
    },
    calendar(time: TimeValue, local: boolean = true): string {
      if (!time) {
        return "Never"
      }
      let date = moment.utc(time)
      if (!date.isValid()) {
        return "Never"
      }
      if (local) {
        date = date.local()
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
  }
})
