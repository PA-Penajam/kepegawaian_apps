import * as React from "react"
import { cva, type VariantProps } from "class-variance-authority"
import { Slot } from "radix-ui"

import { cn } from "@/lib/utils"

const buttonVariants = cva(
  "inline-flex shrink-0 items-center justify-center gap-2 rounded-xl text-sm font-bold whitespace-nowrap transition-all outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
  {
    variants: {
      variant: {
        default: "bg-primary text-primary-foreground border-2 border-foreground drop-shadow-[2px_2px_0_rgba(0,0,0,1)] hover:bg-primary/90 hover:translate-x-[1px] hover:translate-y-[1px] hover:drop-shadow-[1px_1px_0_rgba(0,0,0,1)] active:translate-x-[2px] active:translate-y-[2px] active:drop-shadow-none",
        destructive: "bg-destructive text-white border-2 border-foreground drop-shadow-[2px_2px_0_rgba(0,0,0,1)] hover:bg-destructive/90 hover:translate-x-[1px] hover:translate-y-[1px] hover:drop-shadow-[1px_1px_0_rgba(0,0,0,1)] active:translate-x-[2px] active:translate-y-[2px] active:drop-shadow-none dark:bg-destructive/60",
        outline: "bg-background border-2 border-foreground drop-shadow-[2px_2px_0_rgba(0,0,0,1)] hover:bg-accent hover:text-accent-foreground hover:translate-x-[1px] hover:translate-y-[1px] hover:drop-shadow-[1px_1px_0_rgba(0,0,0,1)] active:translate-x-[2px] active:translate-y-[2px] active:drop-shadow-none dark:border-foreground dark:bg-input/30 dark:hover:bg-input/50",
        secondary: "bg-secondary text-secondary-foreground border-2 border-foreground drop-shadow-[2px_2px_0_rgba(0,0,0,1)] hover:bg-secondary/80 hover:translate-x-[1px] hover:translate-y-[1px] hover:drop-shadow-[1px_1px_0_rgba(0,0,0,1)] active:translate-x-[2px] active:translate-y-[2px] active:drop-shadow-none",
        ghost: "hover:bg-accent hover:text-accent-foreground hover:border-2 hover:border-foreground hover:drop-shadow-[2px_2px_0_rgba(0,0,0,1)] hover:translate-x-[1px] hover:translate-y-[1px] active:translate-x-[2px] active:translate-y-[2px] active:drop-shadow-none dark:hover:bg-accent/50 border-2 border-transparent",
        link: "text-primary underline-offset-4 hover:underline",
      },
      size: {
        default: "h-9 px-4 py-2 has-[>svg]:px-3",
        xs: "h-6 gap-1 rounded-md px-2 text-xs has-[>svg]:px-1.5 [&_svg:not([class*='size-'])]:size-3",
        sm: "h-8 gap-1.5 rounded-md px-3 has-[>svg]:px-2.5",
        lg: "h-10 rounded-md px-6 has-[>svg]:px-4",
        icon: "size-9",
        "icon-xs": "size-6 rounded-md [&_svg:not([class*='size-'])]:size-3",
        "icon-sm": "size-8",
        "icon-lg": "size-10",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  }
)

function Button({
  className,
  variant = "default",
  size = "default",
  asChild = false,
  ...props
}: React.ComponentProps<"button"> &
  VariantProps<typeof buttonVariants> & {
    asChild?: boolean
  }) {
  const Comp = asChild ? Slot.Root : "button"

  return (
    <Comp
      data-slot="button"
      data-variant={variant}
      data-size={size}
      className={cn(buttonVariants({ variant, size, className }))}
      {...props}
    />
  )
}

export { Button, buttonVariants }
