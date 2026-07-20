import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

const buttonVariants = cva(
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-xl text-sm font-bold transition-[color,background-color,border-color,box-shadow,transform] duration-150 outline-none active:translate-y-px disabled:pointer-events-none disabled:translate-y-0 disabled:cursor-not-allowed disabled:opacity-45 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 [&_svg]:shrink-0 focus-visible:border-ring focus-visible:ring-ring/25 focus-visible:ring-[3px] aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40",
  {
    variants: {
      variant: {
        default:
          "bg-primary text-primary-foreground shadow-[0_8px_18px_-12px_color-mix(in_oklab,var(--primary)_82%,transparent)] hover:bg-primary/92 hover:shadow-[0_11px_22px_-12px_color-mix(in_oklab,var(--primary)_88%,transparent)] active:shadow-none",
        destructive:
          "bg-destructive text-white shadow-[0_8px_18px_-12px_color-mix(in_oklab,var(--destructive)_75%,transparent)] hover:bg-destructive/90 active:shadow-none focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40",
        outline:
          "border border-border/90 bg-card shadow-[0_1px_2px_rgba(15,23,42,0.025)] hover:border-primary/25 hover:bg-secondary/65 hover:text-foreground active:bg-secondary",
        secondary:
          "border border-transparent bg-secondary text-secondary-foreground hover:border-border hover:bg-secondary/75 active:bg-secondary/90",
        ghost: "hover:bg-secondary hover:text-foreground active:bg-secondary/80",
        link: "text-primary underline-offset-4 hover:underline",
      },
      size: {
        default: "h-11 px-4 py-2 has-[>svg]:px-3.5",
        sm: "h-9 rounded-lg px-3 has-[>svg]:px-2.5",
        lg: "h-12 rounded-2xl px-6 has-[>svg]:px-4",
        icon: "size-10",
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
  variant,
  size,
  asChild = false,
  ...props
}: React.ComponentProps<"button"> &
  VariantProps<typeof buttonVariants> & {
    asChild?: boolean
  }) {
  const Comp = asChild ? Slot : "button"

  return (
    <Comp
      data-slot="button"
      className={cn(buttonVariants({ variant, size, className }))}
      {...props}
    />
  )
}

export { Button, buttonVariants }
