import { Fragment, jsx, jsxs } from "react/jsx-runtime";
import { Dialog, DialogPanel, Transition, TransitionChild } from "@headlessui/react";
import { Head, Link, createInertiaApp, router, useForm, usePage } from "@inertiajs/react";
import { createContext, forwardRef, useContext, useEffect, useImperativeHandle, useRef, useState } from "react";
import ReactDOMServer from "react-dom/server";
import { route as route$1 } from "ziggy-js";
//#region \0rolldown/runtime.js
var __defProp = Object.defineProperty;
var __exportAll = (all, no_symbols) => {
	let target = {};
	for (var name in all) __defProp(target, name, {
		get: all[name],
		enumerable: true
	});
	if (!no_symbols) __defProp(target, Symbol.toStringTag, { value: "Module" });
	return target;
};
//#endregion
//#region resources/js/Components/ApplicationLogo.jsx
function ApplicationLogo(props) {
	return /* @__PURE__ */ jsx("svg", {
		...props,
		viewBox: "0 0 316 316",
		xmlns: "http://www.w3.org/2000/svg",
		children: /* @__PURE__ */ jsx("path", { d: "M305.8 81.125C305.77 80.995 305.69 80.885 305.65 80.755C305.56 80.525 305.49 80.285 305.37 80.075C305.29 79.935 305.17 79.815 305.07 79.685C304.94 79.515 304.83 79.325 304.68 79.175C304.55 79.045 304.39 78.955 304.25 78.845C304.09 78.715 303.95 78.575 303.77 78.475L251.32 48.275C249.97 47.495 248.31 47.495 246.96 48.275L194.51 78.475C194.33 78.575 194.19 78.725 194.03 78.845C193.89 78.955 193.73 79.045 193.6 79.175C193.45 79.325 193.34 79.515 193.21 79.685C193.11 79.815 192.99 79.935 192.91 80.075C192.79 80.285 192.71 80.525 192.63 80.755C192.58 80.875 192.51 80.995 192.48 81.125C192.38 81.495 192.33 81.875 192.33 82.265V139.625L148.62 164.795V52.575C148.62 52.185 148.57 51.805 148.47 51.435C148.44 51.305 148.36 51.195 148.32 51.065C148.23 50.835 148.16 50.595 148.04 50.385C147.96 50.245 147.84 50.125 147.74 49.995C147.61 49.825 147.5 49.635 147.35 49.485C147.22 49.355 147.06 49.265 146.92 49.155C146.76 49.025 146.62 48.885 146.44 48.785L93.99 18.585C92.64 17.805 90.98 17.805 89.63 18.585L37.18 48.785C37 48.885 36.86 49.035 36.7 49.155C36.56 49.265 36.4 49.355 36.27 49.485C36.12 49.635 36.01 49.825 35.88 49.995C35.78 50.125 35.66 50.245 35.58 50.385C35.46 50.595 35.38 50.835 35.3 51.065C35.25 51.185 35.18 51.305 35.15 51.435C35.05 51.805 35 52.185 35 52.575V232.235C35 233.795 35.84 235.245 37.19 236.025L142.1 296.425C142.33 296.555 142.58 296.635 142.82 296.725C142.93 296.765 143.04 296.835 143.16 296.865C143.53 296.965 143.9 297.015 144.28 297.015C144.66 297.015 145.03 296.965 145.4 296.865C145.5 296.835 145.59 296.775 145.69 296.745C145.95 296.655 146.21 296.565 146.45 296.435L251.36 236.035C252.72 235.255 253.55 233.815 253.55 232.245V174.885L303.81 145.945C305.17 145.165 306 143.725 306 142.155V82.265C305.95 81.875 305.89 81.495 305.8 81.125ZM144.2 227.205L100.57 202.515L146.39 176.135L196.66 147.195L240.33 172.335L208.29 190.625L144.2 227.205ZM244.75 114.995V164.795L226.39 154.225L201.03 139.625V89.825L219.39 100.395L244.75 114.995ZM249.12 57.105L292.81 82.265L249.12 107.425L205.43 82.265L249.12 57.105ZM114.49 184.425L96.13 194.995V85.305L121.49 70.705L139.85 60.135V169.815L114.49 184.425ZM91.76 27.425L135.45 52.585L91.76 77.745L48.07 52.585L91.76 27.425ZM43.67 60.135L62.03 70.705L87.39 85.305V202.545V202.555V202.565C87.39 202.735 87.44 202.895 87.46 203.055C87.49 203.265 87.49 203.485 87.55 203.695V203.705C87.6 203.875 87.69 204.035 87.76 204.195C87.84 204.375 87.89 204.575 87.99 204.745C87.99 204.745 87.99 204.755 88 204.755C88.09 204.905 88.22 205.035 88.33 205.175C88.45 205.335 88.55 205.495 88.69 205.635L88.7 205.645C88.82 205.765 88.98 205.855 89.12 205.965C89.28 206.085 89.42 206.225 89.59 206.325C89.6 206.325 89.6 206.325 89.61 206.335C89.62 206.335 89.62 206.345 89.63 206.345L139.87 234.775V285.065L43.67 229.705V60.135ZM244.75 229.705L148.58 285.075V234.775L219.8 194.115L244.75 179.875V229.705ZM297.2 139.625L253.49 164.795V114.995L278.85 100.395L297.21 89.825V139.625H297.2Z" })
	});
}
//#endregion
//#region resources/js/Components/Dropdown.jsx
var DropDownContext = createContext();
var Dropdown = ({ children }) => {
	const [open, setOpen] = useState(false);
	const toggleOpen = () => {
		setOpen((previousState) => !previousState);
	};
	return /* @__PURE__ */ jsx(DropDownContext.Provider, {
		value: {
			open,
			setOpen,
			toggleOpen
		},
		children: /* @__PURE__ */ jsx("div", {
			className: "relative",
			children
		})
	});
};
var Trigger = ({ children }) => {
	const { open, setOpen, toggleOpen } = useContext(DropDownContext);
	return /* @__PURE__ */ jsxs(Fragment, { children: [/* @__PURE__ */ jsx("div", {
		onClick: toggleOpen,
		children
	}), open && /* @__PURE__ */ jsx("div", {
		className: "fixed inset-0 z-40",
		onClick: () => setOpen(false)
	})] });
};
var Content = ({ align = "right", width = "48", contentClasses = "py-1 bg-white", children }) => {
	const { open, setOpen } = useContext(DropDownContext);
	let alignmentClasses = "origin-top";
	if (align === "left") alignmentClasses = "ltr:origin-top-left rtl:origin-top-right start-0";
	else if (align === "right") alignmentClasses = "ltr:origin-top-right rtl:origin-top-left end-0";
	let widthClasses = "";
	if (width === "48") widthClasses = "w-48";
	return /* @__PURE__ */ jsx(Fragment, { children: /* @__PURE__ */ jsx(Transition, {
		show: open,
		enter: "transition ease-out duration-200",
		enterFrom: "opacity-0 scale-95",
		enterTo: "opacity-100 scale-100",
		leave: "transition ease-in duration-75",
		leaveFrom: "opacity-100 scale-100",
		leaveTo: "opacity-0 scale-95",
		children: /* @__PURE__ */ jsx("div", {
			className: `absolute z-50 mt-2 rounded-md shadow-lg ${alignmentClasses} ${widthClasses}`,
			onClick: () => setOpen(false),
			children: /* @__PURE__ */ jsx("div", {
				className: `rounded-md ring-1 ring-black ring-opacity-5 ` + contentClasses,
				children
			})
		})
	}) });
};
var DropdownLink = ({ className = "", children, ...props }) => {
	return /* @__PURE__ */ jsx(Link, {
		...props,
		className: "block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none " + className,
		children
	});
};
Dropdown.Trigger = Trigger;
Dropdown.Content = Content;
Dropdown.Link = DropdownLink;
//#endregion
//#region resources/js/Components/NavLink.jsx
function NavLink({ active = false, className = "", children, ...props }) {
	return /* @__PURE__ */ jsx(Link, {
		...props,
		className: "inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none " + (active ? "border-indigo-400 text-gray-900 focus:border-indigo-700" : "border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 focus:border-gray-300 focus:text-gray-700") + className,
		children
	});
}
//#endregion
//#region resources/js/Components/ResponsiveNavLink.jsx
function ResponsiveNavLink({ active = false, className = "", children, ...props }) {
	return /* @__PURE__ */ jsx(Link, {
		...props,
		className: `flex w-full items-start border-l-4 py-2 pe-4 ps-3 ${active ? "border-indigo-400 bg-indigo-50 text-indigo-700 focus:border-indigo-700 focus:bg-indigo-100 focus:text-indigo-800" : "border-transparent text-gray-600 hover:border-gray-300 hover:bg-gray-50 hover:text-gray-800 focus:border-gray-300 focus:bg-gray-50 focus:text-gray-800"} text-base font-medium transition duration-150 ease-in-out focus:outline-none ${className}`,
		children
	});
}
//#endregion
//#region resources/js/Layouts/AuthenticatedLayout.jsx
function AuthenticatedLayout({ header, children }) {
	const user = usePage().props.auth.user;
	const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);
	return /* @__PURE__ */ jsxs("div", {
		className: "min-h-screen bg-gray-100",
		children: [
			/* @__PURE__ */ jsxs("nav", {
				className: "border-b border-gray-100 bg-white",
				children: [/* @__PURE__ */ jsx("div", {
					className: "mx-auto max-w-7xl px-4 sm:px-6 lg:px-8",
					children: /* @__PURE__ */ jsxs("div", {
						className: "flex h-16 justify-between",
						children: [
							/* @__PURE__ */ jsxs("div", {
								className: "flex",
								children: [/* @__PURE__ */ jsx("div", {
									className: "flex shrink-0 items-center",
									children: /* @__PURE__ */ jsx(Link, {
										href: "/",
										children: /* @__PURE__ */ jsx(ApplicationLogo, { className: "block h-9 w-auto fill-current text-gray-800" })
									})
								}), /* @__PURE__ */ jsxs("div", {
									className: "hidden space-x-8 sm:-my-px sm:ms-10 sm:flex",
									children: [/* @__PURE__ */ jsx(NavLink, {
										href: route("lab.dashboard"),
										active: route().current("lab.*"),
										children: "Lab"
									}), /* @__PURE__ */ jsx("a", {
										href: "/horizon/dashboard",
										className: "inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium leading-5 text-gray-500 transition duration-150 ease-in-out hover:border-gray-300 hover:text-gray-700 focus:outline-none",
										children: "Horizon"
									})]
								})]
							}),
							/* @__PURE__ */ jsx("div", {
								className: "hidden sm:ms-6 sm:flex sm:items-center",
								children: /* @__PURE__ */ jsx("div", {
									className: "relative ms-3",
									children: /* @__PURE__ */ jsxs(Dropdown, { children: [/* @__PURE__ */ jsx(Dropdown.Trigger, { children: /* @__PURE__ */ jsx("span", {
										className: "inline-flex rounded-md",
										children: /* @__PURE__ */ jsxs("button", {
											type: "button",
											className: "inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none",
											children: [user.name, /* @__PURE__ */ jsx("svg", {
												className: "-me-0.5 ms-2 h-4 w-4",
												xmlns: "http://www.w3.org/2000/svg",
												viewBox: "0 0 20 20",
												fill: "currentColor",
												children: /* @__PURE__ */ jsx("path", {
													fillRule: "evenodd",
													d: "M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z",
													clipRule: "evenodd"
												})
											})]
										})
									}) }), /* @__PURE__ */ jsxs(Dropdown.Content, { children: [/* @__PURE__ */ jsx(Dropdown.Link, {
										href: route("profile.edit"),
										children: "Profile"
									}), /* @__PURE__ */ jsx(Dropdown.Link, {
										href: route("logout"),
										method: "post",
										as: "button",
										children: "Log Out"
									})] })] })
								})
							}),
							/* @__PURE__ */ jsx("div", {
								className: "-me-2 flex items-center sm:hidden",
								children: /* @__PURE__ */ jsx("button", {
									onClick: () => setShowingNavigationDropdown((previousState) => !previousState),
									className: "inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none",
									children: /* @__PURE__ */ jsxs("svg", {
										className: "h-6 w-6",
										stroke: "currentColor",
										fill: "none",
										viewBox: "0 0 24 24",
										children: [/* @__PURE__ */ jsx("path", {
											className: !showingNavigationDropdown ? "inline-flex" : "hidden",
											strokeLinecap: "round",
											strokeLinejoin: "round",
											strokeWidth: "2",
											d: "M4 6h16M4 12h16M4 18h16"
										}), /* @__PURE__ */ jsx("path", {
											className: showingNavigationDropdown ? "inline-flex" : "hidden",
											strokeLinecap: "round",
											strokeLinejoin: "round",
											strokeWidth: "2",
											d: "M6 18L18 6M6 6l12 12"
										})]
									})
								})
							})
						]
					})
				}), /* @__PURE__ */ jsxs("div", {
					className: (showingNavigationDropdown ? "block" : "hidden") + " sm:hidden",
					children: [/* @__PURE__ */ jsxs("div", {
						className: "space-y-1 pb-3 pt-2",
						children: [/* @__PURE__ */ jsx(ResponsiveNavLink, {
							href: route("lab.dashboard"),
							active: route().current("lab.*"),
							children: "Lab"
						}), /* @__PURE__ */ jsx(ResponsiveNavLink, {
							href: "/horizon/dashboard",
							active: false,
							children: "Horizon"
						})]
					}), /* @__PURE__ */ jsxs("div", {
						className: "border-t border-gray-200 pb-1 pt-4",
						children: [/* @__PURE__ */ jsxs("div", {
							className: "px-4",
							children: [/* @__PURE__ */ jsx("div", {
								className: "text-base font-medium text-gray-800",
								children: user.name
							}), /* @__PURE__ */ jsx("div", {
								className: "text-sm font-medium text-gray-500",
								children: user.email
							})]
						}), /* @__PURE__ */ jsxs("div", {
							className: "mt-3 space-y-1",
							children: [/* @__PURE__ */ jsx(ResponsiveNavLink, {
								href: route("profile.edit"),
								children: "Profile"
							}), /* @__PURE__ */ jsx(ResponsiveNavLink, {
								method: "post",
								href: route("logout"),
								as: "button",
								children: "Log Out"
							})]
						})]
					})]
				})]
			}),
			header && /* @__PURE__ */ jsx("header", {
				className: "bg-white shadow",
				children: /* @__PURE__ */ jsx("div", {
					className: "mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8",
					children: header
				})
			}),
			/* @__PURE__ */ jsx("main", { children })
		]
	});
}
//#endregion
//#region resources/js/Components/InputError.jsx
function InputError({ message, className = "", ...props }) {
	return message ? /* @__PURE__ */ jsx("p", {
		...props,
		className: "text-sm text-red-600 " + className,
		children: message
	}) : null;
}
//#endregion
//#region resources/js/Components/InputLabel.jsx
function InputLabel({ value, className = "", children, ...props }) {
	return /* @__PURE__ */ jsx("label", {
		...props,
		className: `block text-sm font-medium text-gray-700 ` + className,
		children: value ? value : children
	});
}
//#endregion
//#region resources/js/Components/PrimaryButton.jsx
function PrimaryButton({ className = "", disabled, children, ...props }) {
	return /* @__PURE__ */ jsx("button", {
		...props,
		className: `inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 active:bg-gray-900 ${disabled && "opacity-25"} ` + className,
		disabled,
		children
	});
}
//#endregion
//#region resources/js/Components/TextInput.jsx
var TextInput_default = forwardRef(function TextInput({ type = "text", className = "", isFocused = false, ...props }, ref) {
	const localRef = useRef(null);
	useImperativeHandle(ref, () => ({ focus: () => localRef.current?.focus() }));
	useEffect(() => {
		if (isFocused) localRef.current?.focus();
	}, [isFocused]);
	return /* @__PURE__ */ jsx("input", {
		...props,
		type,
		className: "rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 " + className,
		ref: localRef
	});
});
//#endregion
//#region Modules/FrontLab/resources/js/Pages/Dashboard.jsx
var Dashboard_exports = /* @__PURE__ */ __exportAll({ default: () => Dashboard });
var JOBS = ["default", "high-priority"];
var CONNECTIONS = [
	"redis-sentinel",
	"rabbit-rs",
	"both"
];
var QUEUES = [
	"default",
	"high-priority",
	"bulk"
];
function StatCard({ label, value, accent = "" }) {
	return /* @__PURE__ */ jsx("div", {
		className: "overflow-hidden bg-white shadow-sm sm:rounded-lg",
		children: /* @__PURE__ */ jsxs("div", {
			className: "flex items-baseline justify-between gap-2 px-4 py-2.5",
			children: [/* @__PURE__ */ jsx("span", {
				className: "text-[11px] font-medium uppercase tracking-wide text-gray-500",
				children: label
			}), /* @__PURE__ */ jsx("span", {
				className: `text-xl font-semibold ${accent || "text-gray-900"}`,
				children: value
			})]
		})
	});
}
function Dashboard() {
	const { stats, success } = usePage().props;
	const [form, setForm] = useState({
		job: "default",
		connection: "redis-sentinel",
		queue: "default",
		count: 10
	});
	const [processing, setProcessing] = useState(false);
	const [error, setError] = useState("");
	useEffect(() => {
		const id = setInterval(() => {
			router.reload({ only: ["stats"] });
		}, 3e3);
		return () => clearInterval(id);
	}, []);
	const dispatch = (e) => {
		e.preventDefault();
		setError("");
		setProcessing(true);
		router.post(route("lab.dispatch"), form, {
			onFinish: () => setProcessing(false),
			onError: () => setError("Dispatch failed.")
		});
	};
	const horizon = stats?.horizon ?? {
		masters: [],
		recent: 0,
		pending: 0,
		failed: 0
	};
	const queues = stats?.queues ?? {};
	return /* @__PURE__ */ jsxs(AuthenticatedLayout, {
		header: /* @__PURE__ */ jsx("h2", {
			className: "text-xl font-semibold leading-tight text-gray-800",
			children: "Queue Lab"
		}),
		children: [/* @__PURE__ */ jsx(Head, { title: "Lab" }), /* @__PURE__ */ jsx("div", {
			className: "py-12",
			children: /* @__PURE__ */ jsxs("div", {
				className: "mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8",
				children: [
					success && /* @__PURE__ */ jsx("div", {
						className: "rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700",
						children: success
					}),
					/* @__PURE__ */ jsxs("div", {
						className: "grid grid-cols-2 gap-3 sm:grid-cols-4",
						children: [
							/* @__PURE__ */ jsx(StatCard, {
								label: "Pending",
								value: horizon.pending,
								accent: "text-amber-600"
							}),
							/* @__PURE__ */ jsx(StatCard, {
								label: "Recent",
								value: horizon.recent
							}),
							/* @__PURE__ */ jsx(StatCard, {
								label: "Failed",
								value: horizon.failed,
								accent: "text-rose-600"
							}),
							/* @__PURE__ */ jsx(StatCard, {
								label: "Masters",
								value: Array.isArray(horizon.masters) ? horizon.masters.length : 0,
								accent: "text-emerald-600"
							})
						]
					}),
					/* @__PURE__ */ jsx("div", {
						className: "overflow-hidden bg-white shadow-sm sm:rounded-lg",
						children: /* @__PURE__ */ jsxs("div", {
							className: "p-6",
							children: [/* @__PURE__ */ jsx("h3", {
								className: "text-sm font-semibold text-gray-900",
								children: "Queues depth (redis / rabbit)"
							}), /* @__PURE__ */ jsx("div", {
								className: "mt-3 flex flex-wrap gap-3",
								children: [
									"default",
									"high-priority",
									"bulk"
								].map((q) => /* @__PURE__ */ jsxs("span", {
									className: "rounded-lg bg-gray-100 px-3 py-1.5 text-sm text-gray-700",
									children: [
										q,
										":",
										" ",
										/* @__PURE__ */ jsx("strong", { children: queues[q]?.redis ?? 0 }),
										" / ",
										/* @__PURE__ */ jsx("strong", { children: queues[q]?.rabbit ?? "–" })
									]
								}, q))
							})]
						})
					}),
					/* @__PURE__ */ jsxs("form", {
						onSubmit: dispatch,
						className: "overflow-hidden bg-white shadow-sm sm:rounded-lg",
						children: [/* @__PURE__ */ jsxs("div", {
							className: "grid grid-cols-1 gap-4 p-6 sm:grid-cols-4",
							children: [
								/* @__PURE__ */ jsxs("div", { children: [/* @__PURE__ */ jsx(InputLabel, {
									htmlFor: "job",
									value: "Job"
								}), /* @__PURE__ */ jsx("select", {
									id: "job",
									className: "mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500",
									value: form.job,
									onChange: (e) => setForm({
										...form,
										job: e.target.value
									}),
									children: JOBS.map((j) => /* @__PURE__ */ jsx("option", {
										value: j,
										children: j
									}, j))
								})] }),
								/* @__PURE__ */ jsxs("div", { children: [/* @__PURE__ */ jsx(InputLabel, {
									htmlFor: "connection",
									value: "Connection"
								}), /* @__PURE__ */ jsx("select", {
									id: "connection",
									className: "mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500",
									value: form.connection,
									onChange: (e) => setForm({
										...form,
										connection: e.target.value
									}),
									children: CONNECTIONS.map((c) => /* @__PURE__ */ jsx("option", {
										value: c,
										children: c
									}, c))
								})] }),
								/* @__PURE__ */ jsxs("div", { children: [/* @__PURE__ */ jsx(InputLabel, {
									htmlFor: "queue",
									value: "Queue"
								}), /* @__PURE__ */ jsx("select", {
									id: "queue",
									className: "mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500",
									value: form.queue,
									onChange: (e) => setForm({
										...form,
										queue: e.target.value
									}),
									children: QUEUES.map((q) => /* @__PURE__ */ jsx("option", {
										value: q,
										children: q
									}, q))
								})] }),
								/* @__PURE__ */ jsxs("div", { children: [/* @__PURE__ */ jsx(InputLabel, {
									htmlFor: "count",
									value: "Count"
								}), /* @__PURE__ */ jsx(TextInput_default, {
									id: "count",
									type: "number",
									min: "1",
									max: "10000",
									className: "mt-1 block w-full",
									value: form.count,
									onChange: (e) => setForm({
										...form,
										count: e.target.value
									})
								})] })
							]
						}), /* @__PURE__ */ jsxs("div", {
							className: "flex items-center gap-3 border-t border-gray-200 px-6 py-4",
							children: [/* @__PURE__ */ jsx(PrimaryButton, {
								disabled: processing,
								children: "Dispatch"
							}), /* @__PURE__ */ jsx(InputError, {
								message: error,
								className: "ms-2"
							})]
						})]
					})
				]
			})
		})]
	});
}
//#endregion
//#region resources/js/Layouts/GuestLayout.jsx
function GuestLayout({ children }) {
	return /* @__PURE__ */ jsxs("div", {
		className: "flex min-h-screen flex-col items-center bg-gray-100 pt-6 sm:justify-center sm:pt-0",
		children: [/* @__PURE__ */ jsx("div", { children: /* @__PURE__ */ jsx(Link, {
			href: "/",
			children: /* @__PURE__ */ jsx(ApplicationLogo, { className: "h-20 w-20 fill-current text-gray-500" })
		}) }), /* @__PURE__ */ jsx("div", {
			className: "mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg",
			children
		})]
	});
}
//#endregion
//#region resources/js/Pages/Auth/ConfirmPassword.jsx
var ConfirmPassword_exports = /* @__PURE__ */ __exportAll({ default: () => ConfirmPassword });
function ConfirmPassword() {
	const { data, setData, post, processing, errors, reset } = useForm({ password: "" });
	const submit = (e) => {
		e.preventDefault();
		post(route("password.confirm"), { onFinish: () => reset("password") });
	};
	return /* @__PURE__ */ jsxs(GuestLayout, { children: [
		/* @__PURE__ */ jsx(Head, { title: "Confirm Password" }),
		/* @__PURE__ */ jsx("div", {
			className: "mb-4 text-sm text-gray-600",
			children: "This is a secure area of the application. Please confirm your password before continuing."
		}),
		/* @__PURE__ */ jsxs("form", {
			onSubmit: submit,
			children: [/* @__PURE__ */ jsxs("div", {
				className: "mt-4",
				children: [
					/* @__PURE__ */ jsx(InputLabel, {
						htmlFor: "password",
						value: "Password"
					}),
					/* @__PURE__ */ jsx(TextInput_default, {
						id: "password",
						type: "password",
						name: "password",
						value: data.password,
						className: "mt-1 block w-full",
						isFocused: true,
						onChange: (e) => setData("password", e.target.value)
					}),
					/* @__PURE__ */ jsx(InputError, {
						message: errors.password,
						className: "mt-2"
					})
				]
			}), /* @__PURE__ */ jsx("div", {
				className: "mt-4 flex items-center justify-end",
				children: /* @__PURE__ */ jsx(PrimaryButton, {
					className: "ms-4",
					disabled: processing,
					children: "Confirm"
				})
			})]
		})
	] });
}
//#endregion
//#region resources/js/Pages/Auth/ForgotPassword.jsx
var ForgotPassword_exports = /* @__PURE__ */ __exportAll({ default: () => ForgotPassword });
function ForgotPassword({ status }) {
	const { data, setData, post, processing, errors } = useForm({ email: "" });
	const submit = (e) => {
		e.preventDefault();
		post(route("password.email"));
	};
	return /* @__PURE__ */ jsxs(GuestLayout, { children: [
		/* @__PURE__ */ jsx(Head, { title: "Forgot Password" }),
		/* @__PURE__ */ jsx("div", {
			className: "mb-4 text-sm text-gray-600",
			children: "Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one."
		}),
		status && /* @__PURE__ */ jsx("div", {
			className: "mb-4 text-sm font-medium text-green-600",
			children: status
		}),
		/* @__PURE__ */ jsxs("form", {
			onSubmit: submit,
			children: [
				/* @__PURE__ */ jsx(TextInput_default, {
					id: "email",
					type: "email",
					name: "email",
					value: data.email,
					className: "mt-1 block w-full",
					isFocused: true,
					onChange: (e) => setData("email", e.target.value)
				}),
				/* @__PURE__ */ jsx(InputError, {
					message: errors.email,
					className: "mt-2"
				}),
				/* @__PURE__ */ jsx("div", {
					className: "mt-4 flex items-center justify-end",
					children: /* @__PURE__ */ jsx(PrimaryButton, {
						className: "ms-4",
						disabled: processing,
						children: "Email Password Reset Link"
					})
				})
			]
		})
	] });
}
//#endregion
//#region resources/js/Components/Checkbox.jsx
function Checkbox({ className = "", ...props }) {
	return /* @__PURE__ */ jsx("input", {
		...props,
		type: "checkbox",
		className: "rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 " + className
	});
}
//#endregion
//#region resources/js/Pages/Auth/Login.jsx
var Login_exports = /* @__PURE__ */ __exportAll({ default: () => Login });
function Login({ status, canResetPassword }) {
	const { data, setData, post, processing, errors, reset } = useForm({
		email: "",
		password: "",
		remember: false
	});
	const submit = (e) => {
		e.preventDefault();
		post(route("login"), { onFinish: () => reset("password") });
	};
	return /* @__PURE__ */ jsxs(GuestLayout, { children: [
		/* @__PURE__ */ jsx(Head, { title: "Log in" }),
		status && /* @__PURE__ */ jsx("div", {
			className: "mb-4 text-sm font-medium text-green-600",
			children: status
		}),
		/* @__PURE__ */ jsxs("form", {
			onSubmit: submit,
			children: [
				/* @__PURE__ */ jsxs("div", { children: [
					/* @__PURE__ */ jsx(InputLabel, {
						htmlFor: "email",
						value: "Email"
					}),
					/* @__PURE__ */ jsx(TextInput_default, {
						id: "email",
						type: "email",
						name: "email",
						value: data.email,
						className: "mt-1 block w-full",
						autoComplete: "username",
						isFocused: true,
						onChange: (e) => setData("email", e.target.value)
					}),
					/* @__PURE__ */ jsx(InputError, {
						message: errors.email,
						className: "mt-2"
					})
				] }),
				/* @__PURE__ */ jsxs("div", {
					className: "mt-4",
					children: [
						/* @__PURE__ */ jsx(InputLabel, {
							htmlFor: "password",
							value: "Password"
						}),
						/* @__PURE__ */ jsx(TextInput_default, {
							id: "password",
							type: "password",
							name: "password",
							value: data.password,
							className: "mt-1 block w-full",
							autoComplete: "current-password",
							onChange: (e) => setData("password", e.target.value)
						}),
						/* @__PURE__ */ jsx(InputError, {
							message: errors.password,
							className: "mt-2"
						})
					]
				}),
				/* @__PURE__ */ jsx("div", {
					className: "mt-4 block",
					children: /* @__PURE__ */ jsxs("label", {
						className: "flex items-center",
						children: [/* @__PURE__ */ jsx(Checkbox, {
							name: "remember",
							checked: data.remember,
							onChange: (e) => setData("remember", e.target.checked)
						}), /* @__PURE__ */ jsx("span", {
							className: "ms-2 text-sm text-gray-600",
							children: "Remember me"
						})]
					})
				}),
				/* @__PURE__ */ jsxs("div", {
					className: "mt-4 flex items-center justify-end",
					children: [canResetPassword && /* @__PURE__ */ jsx(Link, {
						href: route("password.request"),
						className: "rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",
						children: "Forgot your password?"
					}), /* @__PURE__ */ jsx(PrimaryButton, {
						className: "ms-4",
						disabled: processing,
						children: "Log in"
					})]
				})
			]
		})
	] });
}
//#endregion
//#region resources/js/Pages/Auth/Register.jsx
var Register_exports = /* @__PURE__ */ __exportAll({ default: () => Register });
function Register() {
	const { data, setData, post, processing, errors, reset } = useForm({
		name: "",
		email: "",
		password: "",
		password_confirmation: ""
	});
	const submit = (e) => {
		e.preventDefault();
		post(route("register"), { onFinish: () => reset("password", "password_confirmation") });
	};
	return /* @__PURE__ */ jsxs(GuestLayout, { children: [/* @__PURE__ */ jsx(Head, { title: "Register" }), /* @__PURE__ */ jsxs("form", {
		onSubmit: submit,
		children: [
			/* @__PURE__ */ jsxs("div", { children: [
				/* @__PURE__ */ jsx(InputLabel, {
					htmlFor: "name",
					value: "Name"
				}),
				/* @__PURE__ */ jsx(TextInput_default, {
					id: "name",
					name: "name",
					value: data.name,
					className: "mt-1 block w-full",
					autoComplete: "name",
					isFocused: true,
					onChange: (e) => setData("name", e.target.value),
					required: true
				}),
				/* @__PURE__ */ jsx(InputError, {
					message: errors.name,
					className: "mt-2"
				})
			] }),
			/* @__PURE__ */ jsxs("div", {
				className: "mt-4",
				children: [
					/* @__PURE__ */ jsx(InputLabel, {
						htmlFor: "email",
						value: "Email"
					}),
					/* @__PURE__ */ jsx(TextInput_default, {
						id: "email",
						type: "email",
						name: "email",
						value: data.email,
						className: "mt-1 block w-full",
						autoComplete: "username",
						onChange: (e) => setData("email", e.target.value),
						required: true
					}),
					/* @__PURE__ */ jsx(InputError, {
						message: errors.email,
						className: "mt-2"
					})
				]
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "mt-4",
				children: [
					/* @__PURE__ */ jsx(InputLabel, {
						htmlFor: "password",
						value: "Password"
					}),
					/* @__PURE__ */ jsx(TextInput_default, {
						id: "password",
						type: "password",
						name: "password",
						value: data.password,
						className: "mt-1 block w-full",
						autoComplete: "new-password",
						onChange: (e) => setData("password", e.target.value),
						required: true
					}),
					/* @__PURE__ */ jsx(InputError, {
						message: errors.password,
						className: "mt-2"
					})
				]
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "mt-4",
				children: [
					/* @__PURE__ */ jsx(InputLabel, {
						htmlFor: "password_confirmation",
						value: "Confirm Password"
					}),
					/* @__PURE__ */ jsx(TextInput_default, {
						id: "password_confirmation",
						type: "password",
						name: "password_confirmation",
						value: data.password_confirmation,
						className: "mt-1 block w-full",
						autoComplete: "new-password",
						onChange: (e) => setData("password_confirmation", e.target.value),
						required: true
					}),
					/* @__PURE__ */ jsx(InputError, {
						message: errors.password_confirmation,
						className: "mt-2"
					})
				]
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "mt-4 flex items-center justify-end",
				children: [/* @__PURE__ */ jsx(Link, {
					href: route("login"),
					className: "rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",
					children: "Already registered?"
				}), /* @__PURE__ */ jsx(PrimaryButton, {
					className: "ms-4",
					disabled: processing,
					children: "Register"
				})]
			})
		]
	})] });
}
//#endregion
//#region resources/js/Pages/Auth/ResetPassword.jsx
var ResetPassword_exports = /* @__PURE__ */ __exportAll({ default: () => ResetPassword });
function ResetPassword({ token, email }) {
	const { data, setData, post, processing, errors, reset } = useForm({
		token,
		email,
		password: "",
		password_confirmation: ""
	});
	const submit = (e) => {
		e.preventDefault();
		post(route("password.store"), { onFinish: () => reset("password", "password_confirmation") });
	};
	return /* @__PURE__ */ jsxs(GuestLayout, { children: [/* @__PURE__ */ jsx(Head, { title: "Reset Password" }), /* @__PURE__ */ jsxs("form", {
		onSubmit: submit,
		children: [
			/* @__PURE__ */ jsxs("div", { children: [
				/* @__PURE__ */ jsx(InputLabel, {
					htmlFor: "email",
					value: "Email"
				}),
				/* @__PURE__ */ jsx(TextInput_default, {
					id: "email",
					type: "email",
					name: "email",
					value: data.email,
					className: "mt-1 block w-full",
					autoComplete: "username",
					onChange: (e) => setData("email", e.target.value)
				}),
				/* @__PURE__ */ jsx(InputError, {
					message: errors.email,
					className: "mt-2"
				})
			] }),
			/* @__PURE__ */ jsxs("div", {
				className: "mt-4",
				children: [
					/* @__PURE__ */ jsx(InputLabel, {
						htmlFor: "password",
						value: "Password"
					}),
					/* @__PURE__ */ jsx(TextInput_default, {
						id: "password",
						type: "password",
						name: "password",
						value: data.password,
						className: "mt-1 block w-full",
						autoComplete: "new-password",
						isFocused: true,
						onChange: (e) => setData("password", e.target.value)
					}),
					/* @__PURE__ */ jsx(InputError, {
						message: errors.password,
						className: "mt-2"
					})
				]
			}),
			/* @__PURE__ */ jsxs("div", {
				className: "mt-4",
				children: [
					/* @__PURE__ */ jsx(InputLabel, {
						htmlFor: "password_confirmation",
						value: "Confirm Password"
					}),
					/* @__PURE__ */ jsx(TextInput_default, {
						type: "password",
						id: "password_confirmation",
						name: "password_confirmation",
						value: data.password_confirmation,
						className: "mt-1 block w-full",
						autoComplete: "new-password",
						onChange: (e) => setData("password_confirmation", e.target.value)
					}),
					/* @__PURE__ */ jsx(InputError, {
						message: errors.password_confirmation,
						className: "mt-2"
					})
				]
			}),
			/* @__PURE__ */ jsx("div", {
				className: "mt-4 flex items-center justify-end",
				children: /* @__PURE__ */ jsx(PrimaryButton, {
					className: "ms-4",
					disabled: processing,
					children: "Reset Password"
				})
			})
		]
	})] });
}
//#endregion
//#region resources/js/Pages/Auth/VerifyEmail.jsx
var VerifyEmail_exports = /* @__PURE__ */ __exportAll({ default: () => VerifyEmail });
function VerifyEmail({ status }) {
	const { post, processing } = useForm({});
	const submit = (e) => {
		e.preventDefault();
		post(route("verification.send"));
	};
	return /* @__PURE__ */ jsxs(GuestLayout, { children: [
		/* @__PURE__ */ jsx(Head, { title: "Email Verification" }),
		/* @__PURE__ */ jsx("div", {
			className: "mb-4 text-sm text-gray-600",
			children: "Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the email, we will gladly send you another."
		}),
		status === "verification-link-sent" && /* @__PURE__ */ jsx("div", {
			className: "mb-4 text-sm font-medium text-green-600",
			children: "A new verification link has been sent to the email address you provided during registration."
		}),
		/* @__PURE__ */ jsx("form", {
			onSubmit: submit,
			children: /* @__PURE__ */ jsxs("div", {
				className: "mt-4 flex items-center justify-between",
				children: [/* @__PURE__ */ jsx(PrimaryButton, {
					disabled: processing,
					children: "Resend Verification Email"
				}), /* @__PURE__ */ jsx(Link, {
					href: route("logout"),
					method: "post",
					as: "button",
					className: "rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",
					children: "Log Out"
				})]
			})
		})
	] });
}
//#endregion
//#region resources/js/Components/DangerButton.jsx
function DangerButton({ className = "", disabled, children, ...props }) {
	return /* @__PURE__ */ jsx("button", {
		...props,
		className: `inline-flex items-center rounded-md border border-transparent bg-red-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 active:bg-red-700 ${disabled && "opacity-25"} ` + className,
		disabled,
		children
	});
}
//#endregion
//#region resources/js/Components/Modal.jsx
function Modal({ children, show = false, maxWidth = "2xl", closeable = true, onClose = () => {} }) {
	const close = () => {
		if (closeable) onClose();
	};
	const maxWidthClass = {
		sm: "sm:max-w-sm",
		md: "sm:max-w-md",
		lg: "sm:max-w-lg",
		xl: "sm:max-w-xl",
		"2xl": "sm:max-w-2xl"
	}[maxWidth];
	return /* @__PURE__ */ jsx(Transition, {
		show,
		leave: "duration-200",
		children: /* @__PURE__ */ jsxs(Dialog, {
			as: "div",
			id: "modal",
			className: "fixed inset-0 z-50 flex transform items-center overflow-y-auto px-4 py-6 transition-all sm:px-0",
			onClose: close,
			children: [/* @__PURE__ */ jsx(TransitionChild, {
				enter: "ease-out duration-300",
				enterFrom: "opacity-0",
				enterTo: "opacity-100",
				leave: "ease-in duration-200",
				leaveFrom: "opacity-100",
				leaveTo: "opacity-0",
				children: /* @__PURE__ */ jsx("div", { className: "absolute inset-0 bg-gray-500/75" })
			}), /* @__PURE__ */ jsx(TransitionChild, {
				enter: "ease-out duration-300",
				enterFrom: "opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95",
				enterTo: "opacity-100 translate-y-0 sm:scale-100",
				leave: "ease-in duration-200",
				leaveFrom: "opacity-100 translate-y-0 sm:scale-100",
				leaveTo: "opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95",
				children: /* @__PURE__ */ jsx(DialogPanel, {
					className: `mb-6 transform overflow-hidden rounded-lg bg-white shadow-xl transition-all sm:mx-auto sm:w-full ${maxWidthClass}`,
					children
				})
			})]
		})
	});
}
//#endregion
//#region resources/js/Components/SecondaryButton.jsx
function SecondaryButton({ type = "button", className = "", disabled, children, ...props }) {
	return /* @__PURE__ */ jsx("button", {
		...props,
		type,
		className: `inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 ${disabled && "opacity-25"} ` + className,
		disabled,
		children
	});
}
//#endregion
//#region resources/js/Pages/Profile/Partials/DeleteUserForm.jsx
var DeleteUserForm_exports = /* @__PURE__ */ __exportAll({ default: () => DeleteUserForm });
function DeleteUserForm({ className = "" }) {
	const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(false);
	const passwordInput = useRef();
	const { data, setData, delete: destroy, processing, reset, errors, clearErrors } = useForm({ password: "" });
	const confirmUserDeletion = () => {
		setConfirmingUserDeletion(true);
	};
	const deleteUser = (e) => {
		e.preventDefault();
		destroy(route("profile.destroy"), {
			preserveScroll: true,
			onSuccess: () => closeModal(),
			onError: () => passwordInput.current.focus(),
			onFinish: () => reset()
		});
	};
	const closeModal = () => {
		setConfirmingUserDeletion(false);
		clearErrors();
		reset();
	};
	return /* @__PURE__ */ jsxs("section", {
		className: `space-y-6 ${className}`,
		children: [
			/* @__PURE__ */ jsxs("header", { children: [/* @__PURE__ */ jsx("h2", {
				className: "text-lg font-medium text-gray-900",
				children: "Delete Account"
			}), /* @__PURE__ */ jsx("p", {
				className: "mt-1 text-sm text-gray-600",
				children: "Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain."
			})] }),
			/* @__PURE__ */ jsx(DangerButton, {
				onClick: confirmUserDeletion,
				children: "Delete Account"
			}),
			/* @__PURE__ */ jsx(Modal, {
				show: confirmingUserDeletion,
				onClose: closeModal,
				children: /* @__PURE__ */ jsxs("form", {
					onSubmit: deleteUser,
					className: "p-6",
					children: [
						/* @__PURE__ */ jsx("h2", {
							className: "text-lg font-medium text-gray-900",
							children: "Are you sure you want to delete your account?"
						}),
						/* @__PURE__ */ jsx("p", {
							className: "mt-1 text-sm text-gray-600",
							children: "Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account."
						}),
						/* @__PURE__ */ jsxs("div", {
							className: "mt-6",
							children: [
								/* @__PURE__ */ jsx(InputLabel, {
									htmlFor: "password",
									value: "Password",
									className: "sr-only"
								}),
								/* @__PURE__ */ jsx(TextInput_default, {
									id: "password",
									type: "password",
									name: "password",
									ref: passwordInput,
									value: data.password,
									onChange: (e) => setData("password", e.target.value),
									className: "mt-1 block w-3/4",
									isFocused: true,
									placeholder: "Password"
								}),
								/* @__PURE__ */ jsx(InputError, {
									message: errors.password,
									className: "mt-2"
								})
							]
						}),
						/* @__PURE__ */ jsxs("div", {
							className: "mt-6 flex justify-end",
							children: [/* @__PURE__ */ jsx(SecondaryButton, {
								onClick: closeModal,
								children: "Cancel"
							}), /* @__PURE__ */ jsx(DangerButton, {
								className: "ms-3",
								disabled: processing,
								children: "Delete Account"
							})]
						})
					]
				})
			})
		]
	});
}
//#endregion
//#region resources/js/Pages/Profile/Partials/UpdatePasswordForm.jsx
var UpdatePasswordForm_exports = /* @__PURE__ */ __exportAll({ default: () => UpdatePasswordForm });
function UpdatePasswordForm({ className = "" }) {
	const passwordInput = useRef();
	const currentPasswordInput = useRef();
	const { data, setData, errors, put, reset, processing, recentlySuccessful } = useForm({
		current_password: "",
		password: "",
		password_confirmation: ""
	});
	const updatePassword = (e) => {
		e.preventDefault();
		put(route("password.update"), {
			preserveScroll: true,
			onSuccess: () => reset(),
			onError: (errors) => {
				if (errors.password) {
					reset("password", "password_confirmation");
					passwordInput.current.focus();
				}
				if (errors.current_password) {
					reset("current_password");
					currentPasswordInput.current.focus();
				}
			}
		});
	};
	return /* @__PURE__ */ jsxs("section", {
		className,
		children: [/* @__PURE__ */ jsxs("header", { children: [/* @__PURE__ */ jsx("h2", {
			className: "text-lg font-medium text-gray-900",
			children: "Update Password"
		}), /* @__PURE__ */ jsx("p", {
			className: "mt-1 text-sm text-gray-600",
			children: "Ensure your account is using a long, random password to stay secure."
		})] }), /* @__PURE__ */ jsxs("form", {
			onSubmit: updatePassword,
			className: "mt-6 space-y-6",
			children: [
				/* @__PURE__ */ jsxs("div", { children: [
					/* @__PURE__ */ jsx(InputLabel, {
						htmlFor: "current_password",
						value: "Current Password"
					}),
					/* @__PURE__ */ jsx(TextInput_default, {
						id: "current_password",
						ref: currentPasswordInput,
						value: data.current_password,
						onChange: (e) => setData("current_password", e.target.value),
						type: "password",
						className: "mt-1 block w-full",
						autoComplete: "current-password"
					}),
					/* @__PURE__ */ jsx(InputError, {
						message: errors.current_password,
						className: "mt-2"
					})
				] }),
				/* @__PURE__ */ jsxs("div", { children: [
					/* @__PURE__ */ jsx(InputLabel, {
						htmlFor: "password",
						value: "New Password"
					}),
					/* @__PURE__ */ jsx(TextInput_default, {
						id: "password",
						ref: passwordInput,
						value: data.password,
						onChange: (e) => setData("password", e.target.value),
						type: "password",
						className: "mt-1 block w-full",
						autoComplete: "new-password"
					}),
					/* @__PURE__ */ jsx(InputError, {
						message: errors.password,
						className: "mt-2"
					})
				] }),
				/* @__PURE__ */ jsxs("div", { children: [
					/* @__PURE__ */ jsx(InputLabel, {
						htmlFor: "password_confirmation",
						value: "Confirm Password"
					}),
					/* @__PURE__ */ jsx(TextInput_default, {
						id: "password_confirmation",
						value: data.password_confirmation,
						onChange: (e) => setData("password_confirmation", e.target.value),
						type: "password",
						className: "mt-1 block w-full",
						autoComplete: "new-password"
					}),
					/* @__PURE__ */ jsx(InputError, {
						message: errors.password_confirmation,
						className: "mt-2"
					})
				] }),
				/* @__PURE__ */ jsxs("div", {
					className: "flex items-center gap-4",
					children: [/* @__PURE__ */ jsx(PrimaryButton, {
						disabled: processing,
						children: "Save"
					}), /* @__PURE__ */ jsx(Transition, {
						show: recentlySuccessful,
						enter: "transition ease-in-out",
						enterFrom: "opacity-0",
						leave: "transition ease-in-out",
						leaveTo: "opacity-0",
						children: /* @__PURE__ */ jsx("p", {
							className: "text-sm text-gray-600",
							children: "Saved."
						})
					})]
				})
			]
		})]
	});
}
//#endregion
//#region resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.jsx
var UpdateProfileInformationForm_exports = /* @__PURE__ */ __exportAll({ default: () => UpdateProfileInformation });
function UpdateProfileInformation({ mustVerifyEmail, status, className = "" }) {
	const user = usePage().props.auth.user;
	const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
		name: user.name,
		email: user.email
	});
	const submit = (e) => {
		e.preventDefault();
		patch(route("profile.update"));
	};
	return /* @__PURE__ */ jsxs("section", {
		className,
		children: [/* @__PURE__ */ jsxs("header", { children: [/* @__PURE__ */ jsx("h2", {
			className: "text-lg font-medium text-gray-900",
			children: "Profile Information"
		}), /* @__PURE__ */ jsx("p", {
			className: "mt-1 text-sm text-gray-600",
			children: "Update your account's profile information and email address."
		})] }), /* @__PURE__ */ jsxs("form", {
			onSubmit: submit,
			className: "mt-6 space-y-6",
			children: [
				/* @__PURE__ */ jsxs("div", { children: [
					/* @__PURE__ */ jsx(InputLabel, {
						htmlFor: "name",
						value: "Name"
					}),
					/* @__PURE__ */ jsx(TextInput_default, {
						id: "name",
						className: "mt-1 block w-full",
						value: data.name,
						onChange: (e) => setData("name", e.target.value),
						required: true,
						isFocused: true,
						autoComplete: "name"
					}),
					/* @__PURE__ */ jsx(InputError, {
						className: "mt-2",
						message: errors.name
					})
				] }),
				/* @__PURE__ */ jsxs("div", { children: [
					/* @__PURE__ */ jsx(InputLabel, {
						htmlFor: "email",
						value: "Email"
					}),
					/* @__PURE__ */ jsx(TextInput_default, {
						id: "email",
						type: "email",
						className: "mt-1 block w-full",
						value: data.email,
						onChange: (e) => setData("email", e.target.value),
						required: true,
						autoComplete: "username"
					}),
					/* @__PURE__ */ jsx(InputError, {
						className: "mt-2",
						message: errors.email
					})
				] }),
				mustVerifyEmail && user.email_verified_at === null && /* @__PURE__ */ jsxs("div", { children: [/* @__PURE__ */ jsxs("p", {
					className: "mt-2 text-sm text-gray-800",
					children: ["Your email address is unverified.", /* @__PURE__ */ jsx(Link, {
						href: route("verification.send"),
						method: "post",
						as: "button",
						className: "rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",
						children: "Click here to re-send the verification email."
					})]
				}), status === "verification-link-sent" && /* @__PURE__ */ jsx("div", {
					className: "mt-2 text-sm font-medium text-green-600",
					children: "A new verification link has been sent to your email address."
				})] }),
				/* @__PURE__ */ jsxs("div", {
					className: "flex items-center gap-4",
					children: [/* @__PURE__ */ jsx(PrimaryButton, {
						disabled: processing,
						children: "Save"
					}), /* @__PURE__ */ jsx(Transition, {
						show: recentlySuccessful,
						enter: "transition ease-in-out",
						enterFrom: "opacity-0",
						leave: "transition ease-in-out",
						leaveTo: "opacity-0",
						children: /* @__PURE__ */ jsx("p", {
							className: "text-sm text-gray-600",
							children: "Saved."
						})
					})]
				})
			]
		})]
	});
}
//#endregion
//#region resources/js/Pages/Profile/Edit.jsx
var Edit_exports = /* @__PURE__ */ __exportAll({ default: () => Edit });
function Edit({ mustVerifyEmail, status }) {
	return /* @__PURE__ */ jsxs(AuthenticatedLayout, {
		header: /* @__PURE__ */ jsx("h2", {
			className: "text-xl font-semibold leading-tight text-gray-800",
			children: "Profile"
		}),
		children: [/* @__PURE__ */ jsx(Head, { title: "Profile" }), /* @__PURE__ */ jsx("div", {
			className: "py-12",
			children: /* @__PURE__ */ jsxs("div", {
				className: "mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8",
				children: [
					/* @__PURE__ */ jsx("div", {
						className: "bg-white p-4 shadow sm:rounded-lg sm:p-8",
						children: /* @__PURE__ */ jsx(UpdateProfileInformation, {
							mustVerifyEmail,
							status,
							className: "max-w-xl"
						})
					}),
					/* @__PURE__ */ jsx("div", {
						className: "bg-white p-4 shadow sm:rounded-lg sm:p-8",
						children: /* @__PURE__ */ jsx(UpdatePasswordForm, { className: "max-w-xl" })
					}),
					/* @__PURE__ */ jsx("div", {
						className: "bg-white p-4 shadow sm:rounded-lg sm:p-8",
						children: /* @__PURE__ */ jsx(DeleteUserForm, { className: "max-w-xl" })
					})
				]
			})
		})]
	});
}
//#endregion
//#region resources/js/Pages/Welcome.jsx
var Welcome_exports = /* @__PURE__ */ __exportAll({ default: () => Welcome });
function Welcome({ auth, laravelVersion, phpVersion }) {
	const handleImageError = () => {
		document.getElementById("screenshot-container")?.classList.add("!hidden");
		document.getElementById("docs-card")?.classList.add("!row-span-1");
		document.getElementById("docs-card-content")?.classList.add("!flex-row");
		document.getElementById("background")?.classList.add("!hidden");
	};
	return /* @__PURE__ */ jsxs(Fragment, { children: [/* @__PURE__ */ jsx(Head, { title: "Welcome" }), /* @__PURE__ */ jsxs("div", {
		className: "bg-gray-50 text-black/50 dark:bg-black dark:text-white/50",
		children: [/* @__PURE__ */ jsx("img", {
			id: "background",
			className: "absolute -left-20 top-0 max-w-[877px]",
			src: "https://laravel.com/assets/img/welcome/background.svg"
		}), /* @__PURE__ */ jsx("div", {
			className: "relative flex min-h-screen flex-col items-center justify-center selection:bg-[#FF2D20] selection:text-white",
			children: /* @__PURE__ */ jsxs("div", {
				className: "relative w-full max-w-2xl px-6 lg:max-w-7xl",
				children: [
					/* @__PURE__ */ jsxs("header", {
						className: "grid grid-cols-2 items-center gap-2 py-10 lg:grid-cols-3",
						children: [/* @__PURE__ */ jsx("div", {
							className: "flex lg:col-start-2 lg:justify-center",
							children: /* @__PURE__ */ jsx("svg", {
								className: "h-12 w-auto text-white lg:h-16 lg:text-[#FF2D20]",
								viewBox: "0 0 62 65",
								fill: "none",
								xmlns: "http://www.w3.org/2000/svg",
								children: /* @__PURE__ */ jsx("path", {
									d: "M61.8548 14.6253C61.8778 14.7102 61.8895 14.7978 61.8897 14.8858V28.5615C61.8898 28.737 61.8434 28.9095 61.7554 29.0614C61.6675 29.2132 61.5409 29.3392 61.3887 29.4265L49.9104 36.0351V49.1337C49.9104 49.4902 49.7209 49.8192 49.4118 49.9987L25.4519 63.7916C25.3971 63.8227 25.3372 63.8427 25.2774 63.8639C25.255 63.8714 25.2338 63.8851 25.2101 63.8913C25.0426 63.9354 24.8666 63.9354 24.6991 63.8913C24.6716 63.8838 24.6467 63.8689 24.6205 63.8589C24.5657 63.8389 24.5084 63.8215 24.456 63.7916L0.501061 49.9987C0.348882 49.9113 0.222437 49.7853 0.134469 49.6334C0.0465019 49.4816 0.000120578 49.3092 0 49.1337L0 8.10652C0 8.01678 0.0124642 7.92953 0.0348998 7.84477C0.0423783 7.8161 0.0598282 7.78993 0.0697995 7.76126C0.0884958 7.70891 0.105946 7.65531 0.133367 7.6067C0.152063 7.5743 0.179485 7.54812 0.20192 7.51821C0.230588 7.47832 0.256763 7.43719 0.290416 7.40229C0.319084 7.37362 0.356476 7.35243 0.388883 7.32751C0.425029 7.29759 0.457436 7.26518 0.498568 7.2415L12.4779 0.345059C12.6296 0.257786 12.8015 0.211853 12.9765 0.211853C13.1515 0.211853 13.3234 0.257786 13.475 0.345059L25.4531 7.2415H25.4556C25.4955 7.26643 25.5292 7.29759 25.5653 7.32626C25.5977 7.35119 25.6339 7.37362 25.6625 7.40104C25.6974 7.43719 25.7224 7.47832 25.7523 7.51821C25.7735 7.54812 25.8021 7.5743 25.8196 7.6067C25.8483 7.65656 25.8645 7.70891 25.8844 7.76126C25.8944 7.78993 25.9118 7.8161 25.9193 7.84602C25.9423 7.93096 25.954 8.01853 25.9542 8.10652V33.7317L35.9355 27.9844V14.8846C35.9355 14.7973 35.948 14.7088 35.9704 14.6253C35.9792 14.5954 35.9954 14.5692 36.0053 14.5405C36.0253 14.4882 36.0427 14.4346 36.0702 14.386C36.0888 14.3536 36.1163 14.3274 36.1375 14.2975C36.1674 14.2576 36.1923 14.2165 36.2272 14.1816C36.2559 14.1529 36.292 14.1317 36.3244 14.1068C36.3618 14.0769 36.3942 14.0445 36.4341 14.0208L48.4147 7.12434C48.5663 7.03694 48.7383 6.99094 48.9133 6.99094C49.0883 6.99094 49.2602 7.03694 49.4118 7.12434L61.3899 14.0208C61.4323 14.0457 61.4647 14.0769 61.5021 14.1055C61.5333 14.1305 61.5694 14.1529 61.5981 14.1803C61.633 14.2165 61.6579 14.2576 61.6878 14.2975C61.7103 14.3274 61.7377 14.3536 61.7551 14.386C61.7838 14.4346 61.8 14.4882 61.8199 14.5405C61.8312 14.5692 61.8474 14.5954 61.8548 14.6253ZM59.893 27.9844V16.6121L55.7013 19.0252L49.9104 22.3593V33.7317L59.8942 27.9844H59.893ZM47.9149 48.5566V37.1768L42.2187 40.4299L25.953 49.7133V61.2003L47.9149 48.5566ZM1.99677 9.83281V48.5566L23.9562 61.199V49.7145L12.4841 43.2219L12.4804 43.2194L12.4754 43.2169C12.4368 43.1945 12.4044 43.1621 12.3682 43.1347C12.3371 43.1097 12.3009 43.0898 12.2735 43.0624L12.271 43.0586C12.2386 43.0275 12.2162 42.9888 12.1887 42.9539C12.1638 42.9203 12.1339 42.8916 12.114 42.8567L12.1127 42.853C12.0903 42.8156 12.0766 42.7707 12.0604 42.7283C12.0442 42.6909 12.023 42.656 12.013 42.6161C12.0005 42.5688 11.998 42.5177 11.9931 42.4691C11.9881 42.4317 11.9781 42.3943 11.9781 42.3569V15.5801L6.18848 12.2446L1.99677 9.83281ZM12.9777 2.36177L2.99764 8.10652L12.9752 13.8513L22.9541 8.10527L12.9752 2.36177H12.9777ZM18.1678 38.2138L23.9574 34.8809V9.83281L19.7657 12.2459L13.9749 15.5801V40.6281L18.1678 38.2138ZM48.9133 9.14105L38.9344 14.8858L48.9133 20.6305L58.8909 14.8846L48.9133 9.14105ZM47.9149 22.3593L42.124 19.0252L37.9323 16.6121V27.9844L43.7219 31.3174L47.9149 33.7317V22.3593ZM24.9533 47.987L39.59 39.631L46.9065 35.4555L36.9352 29.7145L25.4544 36.3242L14.9907 42.3482L24.9533 47.987Z",
									fill: "currentColor"
								})
							})
						}), /* @__PURE__ */ jsx("nav", {
							className: "-mx-3 flex flex-1 justify-end",
							children: auth.user ? /* @__PURE__ */ jsx(Link, {
								href: route("dashboard"),
								className: "rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white",
								children: "Dashboard"
							}) : /* @__PURE__ */ jsxs(Fragment, { children: [/* @__PURE__ */ jsx(Link, {
								href: route("login"),
								className: "rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white",
								children: "Log in"
							}), /* @__PURE__ */ jsx(Link, {
								href: route("register"),
								className: "rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white",
								children: "Register"
							})] })
						})]
					}),
					/* @__PURE__ */ jsx("main", {
						className: "mt-6",
						children: /* @__PURE__ */ jsxs("div", {
							className: "grid gap-6 lg:grid-cols-2 lg:gap-8",
							children: [
								/* @__PURE__ */ jsxs("a", {
									href: "https://laravel.com/docs",
									id: "docs-card",
									className: "flex flex-col items-start gap-6 overflow-hidden rounded-lg bg-white p-6 shadow-[0px_14px_34px_0px_rgba(0,0,0,0.08)] ring-1 ring-white/[0.05] transition duration-300 hover:text-black/70 hover:ring-black/20 focus:outline-none focus-visible:ring-[#FF2D20] md:row-span-3 lg:p-10 lg:pb-10 dark:bg-zinc-900 dark:ring-zinc-800 dark:hover:text-white/70 dark:hover:ring-zinc-700 dark:focus-visible:ring-[#FF2D20]",
									children: [/* @__PURE__ */ jsxs("div", {
										id: "screenshot-container",
										className: "relative flex w-full flex-1 items-stretch",
										children: [
											/* @__PURE__ */ jsx("img", {
												src: "https://laravel.com/assets/img/welcome/docs-light.svg",
												alt: "Laravel documentation screenshot",
												className: "aspect-video h-full w-full flex-1 rounded-[10px] object-cover object-top drop-shadow-[0px_4px_34px_rgba(0,0,0,0.06)] dark:hidden",
												onError: handleImageError
											}),
											/* @__PURE__ */ jsx("img", {
												src: "https://laravel.com/assets/img/welcome/docs-dark.svg",
												alt: "Laravel documentation screenshot",
												className: "hidden aspect-video h-full w-full flex-1 rounded-[10px] object-cover object-top drop-shadow-[0px_4px_34px_rgba(0,0,0,0.25)] dark:block"
											}),
											/* @__PURE__ */ jsx("div", { className: "absolute -bottom-16 -left-16 h-40 w-[calc(100%+8rem)] bg-gradient-to-b from-transparent via-white to-white dark:via-zinc-900 dark:to-zinc-900" })
										]
									}), /* @__PURE__ */ jsxs("div", {
										className: "relative flex items-center gap-6 lg:items-end",
										children: [/* @__PURE__ */ jsxs("div", {
											id: "docs-card-content",
											className: "flex items-start gap-6 lg:flex-col",
											children: [/* @__PURE__ */ jsx("div", {
												className: "flex size-12 shrink-0 items-center justify-center rounded-full bg-[#FF2D20]/10 sm:size-16",
												children: /* @__PURE__ */ jsxs("svg", {
													className: "size-5 sm:size-6",
													xmlns: "http://www.w3.org/2000/svg",
													fill: "none",
													viewBox: "0 0 24 24",
													children: [/* @__PURE__ */ jsx("path", {
														fill: "#FF2D20",
														d: "M23 4a1 1 0 0 0-1.447-.894L12.224 7.77a.5.5 0 0 1-.448 0L2.447 3.106A1 1 0 0 0 1 4v13.382a1.99 1.99 0 0 0 1.105 1.79l9.448 4.728c.14.065.293.1.447.1.154-.005.306-.04.447-.105l9.453-4.724a1.99 1.99 0 0 0 1.1-1.789V4ZM3 6.023a.25.25 0 0 1 .362-.223l7.5 3.75a.251.251 0 0 1 .138.223v11.2a.25.25 0 0 1-.362.224l-7.5-3.75a.25.25 0 0 1-.138-.22V6.023Zm18 11.2a.25.25 0 0 1-.138.224l-7.5 3.75a.249.249 0 0 1-.329-.099.249.249 0 0 1-.033-.12V9.772a.251.251 0 0 1 .138-.224l7.5-3.75a.25.25 0 0 1 .362.224v11.2Z"
													}), /* @__PURE__ */ jsx("path", {
														fill: "#FF2D20",
														d: "m3.55 1.893 8 4.048a1.008 1.008 0 0 0 .9 0l8-4.048a1 1 0 0 0-.9-1.785l-7.322 3.706a.506.506 0 0 1-.452 0L4.454.108a1 1 0 0 0-.9 1.785H3.55Z"
													})]
												})
											}), /* @__PURE__ */ jsxs("div", {
												className: "pt-3 sm:pt-5 lg:pt-0",
												children: [/* @__PURE__ */ jsx("h2", {
													className: "text-xl font-semibold text-black dark:text-white",
													children: "Documentation"
												}), /* @__PURE__ */ jsx("p", {
													className: "mt-4 text-sm/relaxed",
													children: "Laravel has wonderful documentation covering every aspect of the framework. Whether you are a newcomer or have prior experience with Laravel, we recommend reading our documentation from beginning to end."
												})]
											})]
										}), /* @__PURE__ */ jsx("svg", {
											className: "size-6 shrink-0 stroke-[#FF2D20]",
											xmlns: "http://www.w3.org/2000/svg",
											fill: "none",
											viewBox: "0 0 24 24",
											strokeWidth: "1.5",
											children: /* @__PURE__ */ jsx("path", {
												strokeLinecap: "round",
												strokeLinejoin: "round",
												d: "M4.5 12h15m0 0l-6.75-6.75M19.5 12l-6.75 6.75"
											})
										})]
									})]
								}),
								/* @__PURE__ */ jsxs("a", {
									href: "https://laracasts.com",
									className: "flex items-start gap-4 rounded-lg bg-white p-6 shadow-[0px_14px_34px_0px_rgba(0,0,0,0.08)] ring-1 ring-white/[0.05] transition duration-300 hover:text-black/70 hover:ring-black/20 focus:outline-none focus-visible:ring-[#FF2D20] lg:pb-10 dark:bg-zinc-900 dark:ring-zinc-800 dark:hover:text-white/70 dark:hover:ring-zinc-700 dark:focus-visible:ring-[#FF2D20]",
									children: [
										/* @__PURE__ */ jsx("div", {
											className: "flex size-12 shrink-0 items-center justify-center rounded-full bg-[#FF2D20]/10 sm:size-16",
											children: /* @__PURE__ */ jsx("svg", {
												className: "size-5 sm:size-6",
												xmlns: "http://www.w3.org/2000/svg",
												fill: "none",
												viewBox: "0 0 24 24",
												children: /* @__PURE__ */ jsx("g", {
													fill: "#FF2D20",
													children: /* @__PURE__ */ jsx("path", { d: "M24 8.25a.5.5 0 0 0-.5-.5H.5a.5.5 0 0 0-.5.5v12a2.5 2.5 0 0 0 2.5 2.5h19a2.5 2.5 0 0 0 2.5-2.5v-12Zm-7.765 5.868a1.221 1.221 0 0 1 0 2.264l-6.626 2.776A1.153 1.153 0 0 1 8 18.123v-5.746a1.151 1.151 0 0 1 1.609-1.035l6.626 2.776ZM19.564 1.677a.25.25 0 0 0-.177-.427H15.6a.106.106 0 0 0-.072.03l-4.54 4.543a.25.25 0 0 0 .177.427h3.783c.027 0 .054-.01.073-.03l4.543-4.543ZM22.071 1.318a.047.047 0 0 0-.045.013l-4.492 4.492a.249.249 0 0 0 .038.385.25.25 0 0 0 .14.042h5.784a.5.5 0 0 0 .5-.5v-2a2.5 2.5 0 0 0-1.925-2.432ZM13.014 1.677a.25.25 0 0 0-.178-.427H9.101a.106.106 0 0 0-.073.03l-4.54 4.543a.25.25 0 0 0 .177.427H8.4a.106.106 0 0 0 .073-.03l4.54-4.543ZM6.513 1.677a.25.25 0 0 0-.177-.427H2.5A2.5 2.5 0 0 0 0 3.75v2a.5.5 0 0 0 .5.5h1.4a.106.106 0 0 0 .073-.03l4.54-4.543Z" })
												})
											})
										}),
										/* @__PURE__ */ jsxs("div", {
											className: "pt-3 sm:pt-5",
											children: [/* @__PURE__ */ jsx("h2", {
												className: "text-xl font-semibold text-black dark:text-white",
												children: "Laracasts"
											}), /* @__PURE__ */ jsx("p", {
												className: "mt-4 text-sm/relaxed",
												children: "Laracasts offers thousands of video tutorials on Laravel, PHP, and JavaScript development. Check them out, see for yourself, and massively level up your development skills in the process."
											})]
										}),
										/* @__PURE__ */ jsx("svg", {
											className: "size-6 shrink-0 self-center stroke-[#FF2D20]",
											xmlns: "http://www.w3.org/2000/svg",
											fill: "none",
											viewBox: "0 0 24 24",
											strokeWidth: "1.5",
											children: /* @__PURE__ */ jsx("path", {
												strokeLinecap: "round",
												strokeLinejoin: "round",
												d: "M4.5 12h15m0 0l-6.75-6.75M19.5 12l-6.75 6.75"
											})
										})
									]
								}),
								/* @__PURE__ */ jsxs("a", {
									href: "https://laravel-news.com",
									className: "flex items-start gap-4 rounded-lg bg-white p-6 shadow-[0px_14px_34px_0px_rgba(0,0,0,0.08)] ring-1 ring-white/[0.05] transition duration-300 hover:text-black/70 hover:ring-black/20 focus:outline-none focus-visible:ring-[#FF2D20] lg:pb-10 dark:bg-zinc-900 dark:ring-zinc-800 dark:hover:text-white/70 dark:hover:ring-zinc-700 dark:focus-visible:ring-[#FF2D20]",
									children: [
										/* @__PURE__ */ jsx("div", {
											className: "flex size-12 shrink-0 items-center justify-center rounded-full bg-[#FF2D20]/10 sm:size-16",
											children: /* @__PURE__ */ jsx("svg", {
												className: "size-5 sm:size-6",
												xmlns: "http://www.w3.org/2000/svg",
												fill: "none",
												viewBox: "0 0 24 24",
												children: /* @__PURE__ */ jsxs("g", {
													fill: "#FF2D20",
													children: [
														/* @__PURE__ */ jsx("path", { d: "M8.75 4.5H5.5c-.69 0-1.25.56-1.25 1.25v4.75c0 .69.56 1.25 1.25 1.25h3.25c.69 0 1.25-.56 1.25-1.25V5.75c0-.69-.56-1.25-1.25-1.25Z" }),
														/* @__PURE__ */ jsx("path", { d: "M24 10a3 3 0 0 0-3-3h-2V2.5a2 2 0 0 0-2-2H2a2 2 0 0 0-2 2V20a3.5 3.5 0 0 0 3.5 3.5h17A3.5 3.5 0 0 0 24 20V10ZM3.5 21.5A1.5 1.5 0 0 1 2 20V3a.5.5 0 0 1 .5-.5h14a.5.5 0 0 1 .5.5v17c0 .295.037.588.11.874a.5.5 0 0 1-.484.625L3.5 21.5ZM22 20a1.5 1.5 0 1 1-3 0V9.5a.5.5 0 0 1 .5-.5H21a1 1 0 0 1 1 1v10Z" }),
														/* @__PURE__ */ jsx("path", { d: "M12.751 6.047h2a.75.75 0 0 1 .75.75v.5a.75.75 0 0 1-.75.75h-2A.75.75 0 0 1 12 7.3v-.5a.75.75 0 0 1 .751-.753ZM12.751 10.047h2a.75.75 0 0 1 .75.75v.5a.75.75 0 0 1-.75.75h-2A.75.75 0 0 1 12 11.3v-.5a.75.75 0 0 1 .751-.753ZM4.751 14.047h10a.75.75 0 0 1 .75.75v.5a.75.75 0 0 1-.75.75h-10A.75.75 0 0 1 4 15.3v-.5a.75.75 0 0 1 .751-.753ZM4.75 18.047h7.5a.75.75 0 0 1 .75.75v.5a.75.75 0 0 1-.75.75h-7.5A.75.75 0 0 1 4 19.3v-.5a.75.75 0 0 1 .75-.753Z" })
													]
												})
											})
										}),
										/* @__PURE__ */ jsxs("div", {
											className: "pt-3 sm:pt-5",
											children: [/* @__PURE__ */ jsx("h2", {
												className: "text-xl font-semibold text-black dark:text-white",
												children: "Laravel News"
											}), /* @__PURE__ */ jsx("p", {
												className: "mt-4 text-sm/relaxed",
												children: "Laravel News is a community driven portal and newsletter aggregating all of the latest and most important news in the Laravel ecosystem, including new package releases and tutorials."
											})]
										}),
										/* @__PURE__ */ jsx("svg", {
											className: "size-6 shrink-0 self-center stroke-[#FF2D20]",
											xmlns: "http://www.w3.org/2000/svg",
											fill: "none",
											viewBox: "0 0 24 24",
											strokeWidth: "1.5",
											children: /* @__PURE__ */ jsx("path", {
												strokeLinecap: "round",
												strokeLinejoin: "round",
												d: "M4.5 12h15m0 0l-6.75-6.75M19.5 12l-6.75 6.75"
											})
										})
									]
								}),
								/* @__PURE__ */ jsxs("div", {
									className: "flex items-start gap-4 rounded-lg bg-white p-6 shadow-[0px_14px_34px_0px_rgba(0,0,0,0.08)] ring-1 ring-white/[0.05] lg:pb-10 dark:bg-zinc-900 dark:ring-zinc-800",
									children: [/* @__PURE__ */ jsx("div", {
										className: "flex size-12 shrink-0 items-center justify-center rounded-full bg-[#FF2D20]/10 sm:size-16",
										children: /* @__PURE__ */ jsx("svg", {
											className: "size-5 sm:size-6",
											xmlns: "http://www.w3.org/2000/svg",
											fill: "none",
											viewBox: "0 0 24 24",
											children: /* @__PURE__ */ jsx("g", {
												fill: "#FF2D20",
												children: /* @__PURE__ */ jsx("path", { d: "M16.597 12.635a.247.247 0 0 0-.08-.237 2.234 2.234 0 0 1-.769-1.68c.001-.195.03-.39.084-.578a.25.25 0 0 0-.09-.267 8.8 8.8 0 0 0-4.826-1.66.25.25 0 0 0-.268.181 2.5 2.5 0 0 1-2.4 1.824.045.045 0 0 0-.045.037 12.255 12.255 0 0 0-.093 3.86.251.251 0 0 0 .208.214c2.22.366 4.367 1.08 6.362 2.118a.252.252 0 0 0 .32-.079 10.09 10.09 0 0 0 1.597-3.733ZM13.616 17.968a.25.25 0 0 0-.063-.407A19.697 19.697 0 0 0 8.91 15.98a.25.25 0 0 0-.287.325c.151.455.334.898.548 1.328.437.827.981 1.594 1.619 2.28a.249.249 0 0 0 .32.044 29.13 29.13 0 0 0 2.506-1.99ZM6.303 14.105a.25.25 0 0 0 .265-.274 13.048 13.048 0 0 1 .205-4.045.062.062 0 0 0-.022-.07 2.5 2.5 0 0 1-.777-.982.25.25 0 0 0-.271-.149 11 11 0 0 0-5.6 2.815.255.255 0 0 0-.075.163c-.008.135-.02.27-.02.406.002.8.084 1.598.246 2.381a.25.25 0 0 0 .303.193 19.924 19.924 0 0 1 5.746-.438ZM9.228 20.914a.25.25 0 0 0 .1-.393 11.53 11.53 0 0 1-1.5-2.22 12.238 12.238 0 0 1-.91-2.465.248.248 0 0 0-.22-.187 18.876 18.876 0 0 0-5.69.33.249.249 0 0 0-.179.336c.838 2.142 2.272 4 4.132 5.353a.254.254 0 0 0 .15.048c1.41-.01 2.807-.282 4.117-.802ZM18.93 12.957l-.005-.008a.25.25 0 0 0-.268-.082 2.21 2.21 0 0 1-.41.081.25.25 0 0 0-.217.2c-.582 2.66-2.127 5.35-5.75 7.843a.248.248 0 0 0-.09.299.25.25 0 0 0 .065.091 28.703 28.703 0 0 0 2.662 2.12.246.246 0 0 0 .209.037c2.579-.701 4.85-2.242 6.456-4.378a.25.25 0 0 0 .048-.189 13.51 13.51 0 0 0-2.7-6.014ZM5.702 7.058a.254.254 0 0 0 .2-.165A2.488 2.488 0 0 1 7.98 5.245a.093.093 0 0 0 .078-.062 19.734 19.734 0 0 1 3.055-4.74.25.25 0 0 0-.21-.41 12.009 12.009 0 0 0-10.4 8.558.25.25 0 0 0 .373.281 12.912 12.912 0 0 1 4.826-1.814ZM10.773 22.052a.25.25 0 0 0-.28-.046c-.758.356-1.55.635-2.365.833a.25.25 0 0 0-.022.48c1.252.43 2.568.65 3.893.65.1 0 .2 0 .3-.008a.25.25 0 0 0 .147-.444c-.526-.424-1.1-.917-1.673-1.465ZM18.744 8.436a.249.249 0 0 0 .15.228 2.246 2.246 0 0 1 1.352 2.054c0 .337-.08.67-.23.972a.25.25 0 0 0 .042.28l.007.009a15.016 15.016 0 0 1 2.52 4.6.25.25 0 0 0 .37.132.25.25 0 0 0 .096-.114c.623-1.464.944-3.039.945-4.63a12.005 12.005 0 0 0-5.78-10.258.25.25 0 0 0-.373.274c.547 2.109.85 4.274.901 6.453ZM9.61 5.38a.25.25 0 0 0 .08.31c.34.24.616.561.8.935a.25.25 0 0 0 .3.127.631.631 0 0 1 .206-.034c2.054.078 4.036.772 5.69 1.991a.251.251 0 0 0 .267.024c.046-.024.093-.047.141-.067a.25.25 0 0 0 .151-.23A29.98 29.98 0 0 0 15.957.764a.25.25 0 0 0-.16-.164 11.924 11.924 0 0 0-2.21-.518.252.252 0 0 0-.215.076A22.456 22.456 0 0 0 9.61 5.38Z" })
											})
										})
									}), /* @__PURE__ */ jsxs("div", {
										className: "pt-3 sm:pt-5",
										children: [/* @__PURE__ */ jsx("h2", {
											className: "text-xl font-semibold text-black dark:text-white",
											children: "Vibrant Ecosystem"
										}), /* @__PURE__ */ jsxs("p", {
											className: "mt-4 text-sm/relaxed",
											children: [
												"Laravel's robust library of first-party tools and libraries, such as",
												" ",
												/* @__PURE__ */ jsx("a", {
													href: "https://forge.laravel.com",
													className: "rounded-sm underline hover:text-black focus:outline-none focus-visible:ring-1 focus-visible:ring-[#FF2D20] dark:hover:text-white dark:focus-visible:ring-[#FF2D20]",
													children: "Forge"
												}),
												",",
												" ",
												/* @__PURE__ */ jsx("a", {
													href: "https://vapor.laravel.com",
													className: "rounded-sm underline hover:text-black focus:outline-none focus-visible:ring-1 focus-visible:ring-[#FF2D20] dark:hover:text-white",
													children: "Vapor"
												}),
												",",
												" ",
												/* @__PURE__ */ jsx("a", {
													href: "https://nova.laravel.com",
													className: "rounded-sm underline hover:text-black focus:outline-none focus-visible:ring-1 focus-visible:ring-[#FF2D20] dark:hover:text-white",
													children: "Nova"
												}),
												",",
												" ",
												/* @__PURE__ */ jsx("a", {
													href: "https://envoyer.io",
													className: "rounded-sm underline hover:text-black focus:outline-none focus-visible:ring-1 focus-visible:ring-[#FF2D20] dark:hover:text-white",
													children: "Envoyer"
												}),
												", and",
												" ",
												/* @__PURE__ */ jsx("a", {
													href: "https://herd.laravel.com",
													className: "rounded-sm underline hover:text-black focus:outline-none focus-visible:ring-1 focus-visible:ring-[#FF2D20] dark:hover:text-white",
													children: "Herd"
												}),
												" ",
												"help you take your projects to the next level. Pair them with powerful open source libraries like",
												" ",
												/* @__PURE__ */ jsx("a", {
													href: "https://laravel.com/docs/billing",
													className: "rounded-sm underline hover:text-black focus:outline-none focus-visible:ring-1 focus-visible:ring-[#FF2D20] dark:hover:text-white",
													children: "Cashier"
												}),
												",",
												" ",
												/* @__PURE__ */ jsx("a", {
													href: "https://laravel.com/docs/dusk",
													className: "rounded-sm underline hover:text-black focus:outline-none focus-visible:ring-1 focus-visible:ring-[#FF2D20] dark:hover:text-white",
													children: "Dusk"
												}),
												",",
												" ",
												/* @__PURE__ */ jsx("a", {
													href: "https://laravel.com/docs/broadcasting",
													className: "rounded-sm underline hover:text-black focus:outline-none focus-visible:ring-1 focus-visible:ring-[#FF2D20] dark:hover:text-white",
													children: "Echo"
												}),
												",",
												" ",
												/* @__PURE__ */ jsx("a", {
													href: "https://laravel.com/docs/horizon",
													className: "rounded-sm underline hover:text-black focus:outline-none focus-visible:ring-1 focus-visible:ring-[#FF2D20] dark:hover:text-white",
													children: "Horizon"
												}),
												",",
												" ",
												/* @__PURE__ */ jsx("a", {
													href: "https://laravel.com/docs/sanctum",
													className: "rounded-sm underline hover:text-black focus:outline-none focus-visible:ring-1 focus-visible:ring-[#FF2D20] dark:hover:text-white",
													children: "Sanctum"
												}),
												",",
												" ",
												/* @__PURE__ */ jsx("a", {
													href: "https://laravel.com/docs/telescope",
													className: "rounded-sm underline hover:text-black focus:outline-none focus-visible:ring-1 focus-visible:ring-[#FF2D20] dark:hover:text-white",
													children: "Telescope"
												}),
												", and more."
											]
										})]
									})]
								})
							]
						})
					}),
					/* @__PURE__ */ jsxs("footer", {
						className: "py-16 text-center text-sm text-black dark:text-white/70",
						children: [
							"Laravel v",
							laravelVersion,
							" (PHP v",
							phpVersion,
							")"
						]
					})
				]
			})
		})]
	})] });
}
//#endregion
//#region resources/js/ssr.jsx
function render(page) {
	return createInertiaApp({
		page,
		render: ReactDOMServer.renderToString,
		title: (title) => `${title} - Laravel`,
		resolve: (name) => {
			const pages = /* #__PURE__ */ Object.assign({
				"../../Modules/FrontLab/resources/js/Pages/Dashboard.jsx": Dashboard_exports,
				"./Pages/Auth/ConfirmPassword.jsx": ConfirmPassword_exports,
				"./Pages/Auth/ForgotPassword.jsx": ForgotPassword_exports,
				"./Pages/Auth/Login.jsx": Login_exports,
				"./Pages/Auth/Register.jsx": Register_exports,
				"./Pages/Auth/ResetPassword.jsx": ResetPassword_exports,
				"./Pages/Auth/VerifyEmail.jsx": VerifyEmail_exports,
				"./Pages/Profile/Edit.jsx": Edit_exports,
				"./Pages/Profile/Partials/DeleteUserForm.jsx": DeleteUserForm_exports,
				"./Pages/Profile/Partials/UpdatePasswordForm.jsx": UpdatePasswordForm_exports,
				"./Pages/Profile/Partials/UpdateProfileInformationForm.jsx": UpdateProfileInformationForm_exports,
				"./Pages/Welcome.jsx": Welcome_exports
			});
			const [module, ...rest] = name.split("/");
			return pages[`../../Modules/${module}/resources/js/Pages/${rest.join("/")}.jsx`] ?? pages[`./Pages/${name}.jsx`];
		},
		setup: ({ App, props }) => {
			global.route = (name, params, absolute) => route$1(name, params, absolute, page.props.ziggy);
			return /* @__PURE__ */ jsx(App, { ...props });
		}
	});
}
//#endregion
export { render as default };
