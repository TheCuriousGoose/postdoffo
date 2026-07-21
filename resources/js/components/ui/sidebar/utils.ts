import type { ComputedRef, Ref } from "vue"
import { createContext } from "reka-ui"

// Sidebar-related cookie max-age, shared with the width cookie below (the
// sidebar can no longer be collapsed, only resized, so there's no longer an
// open/collapsed cookie of its own).
export const SIDEBAR_COOKIE_MAX_AGE = 60 * 60 * 24 * 7
export const SIDEBAR_WIDTH = "16rem"
export const SIDEBAR_WIDTH_MOBILE = "18rem"
export const SIDEBAR_WIDTH_ICON = "3rem"

export const SIDEBAR_WIDTH_COOKIE_NAME = "sidebar_width"
export const SIDEBAR_MIN_WIDTH_PX = 240
export const SIDEBAR_MAX_WIDTH_PX = 480

export const [useSidebar, provideSidebarContext] = createContext<{
  state: ComputedRef<"expanded" | "collapsed">
  open: Ref<boolean>
  isMobile: Ref<boolean>
  openMobile: Ref<boolean>
  setOpenMobile: (value: boolean) => void
  width: Ref<string>
  setWidthPx: (px: number) => void
  resizing: Ref<boolean>
  setResizing: (value: boolean) => void
}>("Sidebar")
