import { motion, useReducedMotion } from "framer-motion";
import {
    AlertTriangle,
    ArrowUpRight,
    BarChart3,
    Boxes,
    CreditCard,
    LayoutDashboard,
    Package,
    Receipt,
    ScanLine,
    Search,
    ShieldCheck,
    Truck,
} from "lucide-react";

/**
 * The product shots on the site are the product, drawn in markup rather than
 * photographed: the same sidebar, the same card shapes, the same tabular
 * figures the application uses. They stay sharp at any size, they cost a few
 * kilobytes instead of a megabyte, and they cannot go stale against a redesign
 * the way a screenshot does.
 */

type Screen = "dashboard" | "pos" | "stock";

const SIDEBAR: { icon: typeof LayoutDashboard; label: string }[] = [
    { icon: LayoutDashboard, label: "Dashboard" },
    { icon: Receipt, label: "Sales" },
    { icon: Boxes, label: "Stock" },
    { icon: Package, label: "Procurement" },
    { icon: Truck, label: "Warehouse" },
    { icon: BarChart3, label: "Reports" },
];

export function AppPreview({
    screen = "dashboard",
    className = "",
}: {
    screen?: Screen;
    className?: string;
}) {
    const quiet = useReducedMotion();
    const active =
        screen === "pos" ? "Sales" : screen === "stock" ? "Stock" : "Dashboard";

    return (
        <div
            className={`overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/10 ${className}`}
        >
            {/* Window chrome */}
            <div className="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-4 py-2.5">
                <span className="flex gap-1.5">
                    <span className="h-2.5 w-2.5 rounded-full bg-rose-400" />
                    <span className="h-2.5 w-2.5 rounded-full bg-amber-400" />
                    <span className="h-2.5 w-2.5 rounded-full bg-emerald-400" />
                </span>
                <span className="mx-auto flex items-center gap-1.5 rounded-md bg-white px-3 py-1 text-[11px] text-slate-400 ring-1 ring-slate-200">
                    <ShieldCheck
                        className="h-3 w-3 text-blue-500"
                        aria-hidden
                    />
                    stockpoint.solforbs.com
                </span>
            </div>

            <div className="flex">
                {/* Sidebar */}
                <nav className="hidden w-40 shrink-0 flex-col gap-0.5 border-r border-slate-200 bg-slate-50/70 p-2.5 sm:flex">
                    <div className="mb-2 flex items-center gap-2 px-1.5 py-1">
                        <span className="flex h-6 w-6 items-center justify-center rounded-lg bg-blue-600">
                            <ShieldCheck
                                className="h-3.5 w-3.5 text-white"
                                aria-hidden
                            />
                        </span>
                        <span className="text-[11px] font-bold tracking-tight text-slate-900">
                            PharmaPoint
                        </span>
                    </div>
                    {SIDEBAR.map((item) => (
                        <span
                            key={item.label}
                            className={`flex items-center gap-2 rounded-lg px-2 py-1.5 text-[11px] font-medium ${
                                item.label === active
                                    ? "bg-blue-600 text-white"
                                    : "text-slate-500"
                            }`}
                        >
                            <item.icon className="h-3.5 w-3.5" aria-hidden />
                            {item.label}
                        </span>
                    ))}
                </nav>

                <div className="@container min-w-0 flex-1 bg-white p-4">
                    {screen === "dashboard" && (
                        <DashboardScreen quiet={quiet} />
                    )}
                    {screen === "pos" && <PosScreen />}
                    {screen === "stock" && <StockScreen quiet={quiet} />}
                </div>
            </div>
        </div>
    );
}

function DashboardScreen({ quiet }: { quiet: boolean | null }) {
    const bars = [38, 52, 44, 68, 57, 81, 63, 92, 74, 86, 70, 96];

    return (
        <>
            <div className="flex items-baseline justify-between">
                <h3 className="text-sm font-bold text-slate-900">
                    Main Branch
                </h3>
                <span className="text-[10px] text-slate-400">Today</span>
            </div>

            {/* Three cards where the frame is wide, one stat per row where it is not —
                the hero's small product shot has a sidebar eating half its width. */}
            <div className="mt-3 grid gap-2 @[18rem]:grid-cols-3 @[18rem]:gap-2.5">
                {[
                    {
                        label: "Sales today",
                        value: "KES 128,400",
                        delta: "+12%",
                        tone: "text-emerald-600",
                    },
                    {
                        label: "Owed to us",
                        value: "KES 486,900",
                        delta: "31 invoices",
                        tone: "text-slate-400",
                    },
                    {
                        label: "Expiring ≤ 90d",
                        value: "14 batches",
                        delta: "Review",
                        tone: "text-amber-600",
                    },
                ].map((card) => (
                    <div
                        key={card.label}
                        className="flex min-w-0 items-baseline justify-between gap-2 rounded-xl border border-slate-200 p-2 @[18rem]:block @[18rem]:p-2.5"
                    >
                        <div className="text-[9px] uppercase tracking-wide text-slate-400">
                            {card.label}
                        </div>
                        <div className="whitespace-nowrap text-[13px] font-bold tabular-nums text-slate-900 @[18rem]:mt-1">
                            {card.value}
                        </div>
                        <div
                            className={`hidden items-center gap-0.5 text-[9px] font-semibold @[18rem]:mt-0.5 @[18rem]:flex ${card.tone}`}
                        >
                            {card.delta === "+12%" && (
                                <ArrowUpRight
                                    className="h-2.5 w-2.5"
                                    aria-hidden
                                />
                            )}
                            {card.delta}
                        </div>
                    </div>
                ))}
            </div>

            <div className="mt-3 rounded-xl border border-slate-200 p-3">
                <div className="flex items-center justify-between">
                    <span className="text-[10px] font-semibold text-slate-700">
                        Takings, last 12 days
                    </span>
                    <span className="text-[9px] text-slate-400">KES ’000</span>
                </div>
                <div className="mt-2.5 flex h-20 items-end gap-1.5">
                    {bars.map((height, index) => (
                        <motion.span
                            key={index}
                            initial={quiet ? undefined : { height: 0 }}
                            whileInView={
                                quiet ? undefined : { height: `${height}%` }
                            }
                            viewport={{ once: true }}
                            transition={{
                                duration: 0.5,
                                delay: index * 0.04,
                                ease: [0.22, 1, 0.36, 1],
                            }}
                            style={quiet ? { height: `${height}%` } : undefined}
                            className={`flex-1 rounded-sm ${index === bars.length - 1 ? "bg-blue-600" : "bg-blue-600/25"}`}
                        />
                    ))}
                </div>
            </div>

            <div className="mt-2.5 flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2">
                <AlertTriangle
                    className="h-3.5 w-3.5 shrink-0 text-amber-600"
                    aria-hidden
                />
                <span className="text-[10px] font-medium text-amber-800">
                    PPB premises licence expires in 27 days
                </span>
            </div>
        </>
    );
}

function PosScreen() {
    const lines = [
        ["Amoxicillin 500mg caps", "2 BOX", "1,000.00"],
        ["Paracetamol 500mg tabs", "5 STR", "250.00"],
        ["ORS sachets", "10 EA", "300.00"],
    ];

    return (
        <div className="grid gap-2.5 sm:grid-cols-5">
            <div className="sm:col-span-3">
                <div className="flex items-center gap-2 rounded-lg border border-slate-200 px-2.5 py-1.5">
                    <Search
                        className="h-3.5 w-3.5 text-slate-400"
                        aria-hidden
                    />
                    <span className="text-[10px] text-slate-400">
                        Scan or search a medicine…
                    </span>
                    <ScanLine
                        className="ml-auto h-3.5 w-3.5 text-blue-500"
                        aria-hidden
                    />
                </div>
                <div className="mt-2.5 grid grid-cols-3 gap-2">
                    {[
                        "Amoxil 500",
                        "Panadol",
                        "ORS",
                        "Zinc 20mg",
                        "Ventolin",
                        "Flagyl 400",
                    ].map((item) => (
                        <div
                            key={item}
                            className="rounded-lg border border-slate-200 p-2"
                        >
                            <div className="h-8 rounded bg-slate-100" />
                            <div className="mt-1.5 truncate text-[9px] font-semibold text-slate-700">
                                {item}
                            </div>
                            <div className="text-[9px] text-slate-400">
                                In stock
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            <div className="rounded-xl border border-slate-200 p-2.5 sm:col-span-2">
                <div className="text-[10px] font-semibold text-slate-700">
                    Cart · Walk-in
                </div>
                <div className="mt-2 space-y-1.5">
                    {lines.map(([name, qty, total]) => (
                        <div
                            key={name}
                            className="flex items-baseline justify-between gap-2"
                        >
                            <span className="min-w-0 flex-1 truncate text-[9px] text-slate-600">
                                {name}
                            </span>
                            <span className="text-[9px] text-slate-400">
                                {qty}
                            </span>
                            <span className="text-[9px] font-semibold tabular-nums text-slate-900">
                                {total}
                            </span>
                        </div>
                    ))}
                </div>
                <div className="mt-2.5 border-t border-slate-100 pt-2">
                    <div className="flex items-baseline justify-between">
                        <span className="text-[10px] font-semibold text-slate-700">
                            Total
                        </span>
                        <span className="text-[13px] font-bold tabular-nums text-slate-900">
                            KES 1,550.00
                        </span>
                    </div>
                    <div className="mt-2 grid grid-cols-2 gap-1.5">
                        <span className="flex items-center justify-center gap-1 rounded-lg bg-emerald-600 py-1.5 text-[9px] font-bold text-white">
                            <CreditCard className="h-3 w-3" aria-hidden />{" "}
                            M-PESA
                        </span>
                        <span className="rounded-lg bg-slate-900 py-1.5 text-center text-[9px] font-bold text-white">
                            Cash
                        </span>
                    </div>
                </div>
            </div>
        </div>
    );
}

function StockScreen({ quiet }: { quiet: boolean | null }) {
    const rows = [
        [
            "Amoxicillin 500mg caps",
            "B-2291",
            "12 Mar 2027",
            "1,840",
            "text-slate-600",
        ],
        [
            "Insulin glargine 100IU",
            "B-0417",
            "02 Nov 2026",
            "96",
            "text-amber-600",
        ],
        ["Oxytocin 10IU inj", "B-1180", "18 Dec 2026", "240", "text-slate-600"],
        [
            "Artemether/Lum 20/120",
            "B-3325",
            "30 Sep 2026",
            "58",
            "text-rose-600",
        ],
    ];

    return (
        <>
            <div className="flex items-center justify-between">
                <h3 className="text-sm font-bold text-slate-900">
                    Stock on hand
                </h3>
                <span className="rounded-md bg-blue-50 px-2 py-0.5 text-[9px] font-semibold text-blue-700">
                    Drugs · Antibiotics
                </span>
            </div>

            <div className="mt-2.5 flex gap-1.5">
                {["Drugs", "Cold chain", "Vaccines", "Supplies"].map(
                    (heading, index) => (
                        <span
                            key={heading}
                            className={`rounded-full px-2 py-0.5 text-[9px] font-semibold ${
                                index === 0
                                    ? "bg-slate-900 text-white"
                                    : "bg-slate-100 text-slate-500"
                            }`}
                        >
                            {heading}
                        </span>
                    ),
                )}
            </div>

            <table className="mt-2.5 w-full">
                <thead>
                    <tr className="text-left text-[9px] uppercase tracking-wide text-slate-400">
                        <th className="pb-1.5 font-medium">Product</th>
                        <th className="pb-1.5 font-medium">Batch</th>
                        <th className="pb-1.5 font-medium">Expires</th>
                        <th className="pb-1.5 text-right font-medium">
                            On hand
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map(([product, batch, expiry, qty, tone], index) => (
                        <motion.tr
                            key={batch}
                            initial={quiet ? undefined : { opacity: 0, x: -8 }}
                            whileInView={
                                quiet ? undefined : { opacity: 1, x: 0 }
                            }
                            viewport={{ once: true }}
                            transition={{ duration: 0.35, delay: index * 0.07 }}
                            className="border-t border-slate-100"
                        >
                            <td className="py-1.5 text-[10px] font-medium text-slate-800">
                                {product}
                            </td>
                            <td className="py-1.5 font-mono text-[9px] text-slate-500">
                                {batch}
                            </td>
                            <td
                                className={`py-1.5 text-[10px] font-medium ${tone}`}
                            >
                                {expiry}
                            </td>
                            <td className="py-1.5 text-right text-[10px] font-semibold tabular-nums text-slate-900">
                                {qty}
                            </td>
                        </motion.tr>
                    ))}
                </tbody>
            </table>
        </>
    );
}
