import { getCurrentUser } from '@nextcloud/auth'
import {
	showInfo,
	showError,
	getFilePickerBuilder,
	FilePickerType,
} from '@nextcloud/dialogs'
import * as network from './network.js'
import { calculate } from './calc.ts'

export function importCospendProject(importBeginCallback, importSuccessCallback, importEndCallback) {
	const picker = getFilePickerBuilder(t('cospend', 'Choose csv project file'))
		.setMultiSelect(false)
		.setType(FilePickerType.Choose)
		.addMimeTypeFilter('text/csv')
		// .allowDirectories()
		// .startAt(this.outputDir)
		.addButton({
			label: t('cospend', 'Choose'),
			variant: 'primary',
			callback: (nodes) => {
				const node = nodes[0]
				const path = node.path
				importProject(path, false, importBeginCallback, importSuccessCallback, importEndCallback)
			},
		})
		.build()
	picker.pick()
	/*
		.then(async (path) => {
			importProject(path, false, importBeginCallback, importSuccessCallback, importEndCallback)
		})
	*/
}

export function importSWProject(importBeginCallback, importSuccessCallback, importEndCallback) {
	const picker = getFilePickerBuilder(t('cospend', 'Choose SplitWise project file'))
		.setMultiSelect(false)
		.setType(FilePickerType.Choose)
		.addMimeTypeFilter('text/csv')
		// .allowDirectories()
		// .startAt(this.outputDir)
		.addButton({
			label: t('cospend', 'Choose'),
			variant: 'primary',
			callback: (nodes) => {
				const node = nodes[0]
				const path = node.path
				importProject(path, true, importBeginCallback, importSuccessCallback, importEndCallback)
			},
		})
		.build()
	picker.pick()
	/*
		.then(async (path) => {
			importProject(path, true, importBeginCallback, importSuccessCallback, importEndCallback)
		})
	*/
}

export function importProject(targetPath, isSplitWise = false, importBeginCallback, importSuccessCallback, importEndCallback) {
	if (importBeginCallback) {
		importBeginCallback()
	}
	network.importProject(targetPath, isSplitWise).then((response) => {
		if (importSuccessCallback) {
			importSuccessCallback(response.data.ocs.data)
		}
	}).catch((error) => {
		showError(
			t('cospend', 'Failed to import project file')
			+ ': ' + (error.response?.data?.ocs?.meta?.message || error.response?.data?.ocs?.data?.message || error.response?.request?.responseText),
		)
	}).then(() => {
		if (importEndCallback) {
			importEndCallback()
		}
	})
}

export function getMemberName(projectid, memberid) {
	return OCA.Cospend.state.members[projectid][memberid].name
}

export function getSmartMemberName(projectid, memberid) {
	return (!OCA.Cospend.state.pageIsPublic && OCA.Cospend.state.members[projectid][memberid].userid === getCurrentUser().uid)
		? t('cospend', 'You')
		: getMemberName(projectid, memberid)
}

export function getSortedMembers(members, order) {
	if (order === 'name') {
		return members.slice().sort((a, b) => {
			return strcmp(a.name, b.name)
		})
	} else if (order === 'balance') {
		return members.slice().sort((a, b) => {
			return b.balance - a.balance
		})
	}
	return members
}

export function getCategory(projectid, catId) {
	let icon, name, color
	if (catId in OCA.Cospend.state.hardCodedCategories) {
		name = OCA.Cospend.state.hardCodedCategories[catId].name
		icon = OCA.Cospend.state.hardCodedCategories[catId].icon
		color = OCA.Cospend.state.hardCodedCategories[catId].color
	} else if (catId in OCA.Cospend.state.projects[projectid].categories) {
		name = OCA.Cospend.state.projects[projectid].categories[catId].name || ''
		icon = OCA.Cospend.state.projects[projectid].categories[catId].icon || ''
		color = OCA.Cospend.state.projects[projectid].categories[catId].color || 'red'
	} else {
		name = t('cospend', 'No category')
		icon = ''
		color = '#000000'
	}

	return {
		id: catId,
		name,
		icon,
		color,
	}
}

export function getPaymentMode(projectid, pmId) {
	let icon, name, color
	if (pmId in OCA.Cospend.state.projects[projectid].paymentmodes) {
		name = OCA.Cospend.state.projects[projectid].paymentmodes[pmId].name || ''
		icon = OCA.Cospend.state.projects[projectid].paymentmodes[pmId].icon || ''
		color = OCA.Cospend.state.projects[projectid].paymentmodes[pmId].color || 'red'
	} else {
		name = t('cospend', 'No payment mode')
		icon = ''
		color = '#000000'
	}

	return {
		id: pmId,
		name,
		icon,
		color,
	}
}

export function strcmp(a, b) {
	const la = a.toLowerCase()
	const lb = b.toLowerCase()
	return la > lb
		? 1
		: la < lb
			? -1
			: 0
}

export function evalAlgebricFormula(formula) {
	let calc = 'a'
	try {
		const saneFormula = formula.replace(/[^-()\d/*+.]/g, '')
		calc = parseFloat(calculate(saneFormula).toFixed(12))
	} catch (err) {
		console.error(err)
	}
	return calc
}

export function hexToRgb(hex) {
	const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex)
	return result
		? {
			r: parseInt(result[1], 16),
			g: parseInt(result[2], 16),
			b: parseInt(result[3], 16),
		}
		: null
}

function componentToHex(c) {
	const hex = c.toString(16)
	return hex.length === 1 ? '0' + hex : hex
}

export function rgbObjToHex(o) {
	return rgbToHex(o.r, o.g, o.b)
}

function rgbToHex(r, g, b) {
	return '#' + componentToHex(parseInt(r)) + componentToHex(parseInt(g)) + componentToHex(parseInt(b))
}

export function hexToDarkerHex(hex, lowerTo = 100) {
	const rgb = hexToRgb(hex)
	while (getColorBrightness(rgb) > lowerTo) {
		if (rgb.r > 0) {
			rgb.r--
		}
		if (rgb.g > 0) {
			rgb.g--
		}
		if (rgb.b > 0) {
			rgb.b--
		}
	}
	return rgbToHex(rgb.r, rgb.g, rgb.b)
}

export function hexToBrighterHex(hex, raiseTo = 140) {
	const rgb = hexToRgb(hex)
	while (getColorBrightness(rgb) < raiseTo) {
		if (rgb.r < 255) {
			rgb.r++
		}
		if (rgb.g < 255) {
			rgb.g++
		}
		if (rgb.b < 255) {
			rgb.b++
		}
	}
	return rgbToHex(rgb.r, rgb.g, rgb.b)
}

export function getComplementaryColor(hex) {
	const rgb = hexToRgb(hex)
	return rgbToHex(255 - rgb.r, 255 - rgb.g, 255 - rgb.b)

}

// this formula was found here : https://stackoverflow.com/a/596243/7692836
export function getColorBrightness(rgb) {
	return 0.2126 * rgb.r + 0.7152 * rgb.g + 0.0722 * rgb.b
}

export function Timer(callback, mydelay) {
	let timerId
	let start
	let remaining = mydelay

	this.pause = function() {
		window.clearTimeout(timerId)
		remaining -= new Date() - start
	}

	this.resume = function() {
		start = new Date()
		window.clearTimeout(timerId)
		timerId = window.setTimeout(callback, remaining)
	}

	this.resume()
}

let mytimer = 0

export function delay(callback, ms) {
	return function() {
		const context = this
		const args = arguments
		clearTimeout(mytimer)
		mytimer = setTimeout(function() {
			callback.apply(context, args)
		}, ms || 0)
	}
}

export function pad(n) {
	return (n < 10) ? ('0' + n) : n
}

export function endsWith(str, suffix) {
	return str.indexOf(suffix, str.length - suffix.length) !== -1
}

export function basename(str) {
	let base = String(str).substring(str.lastIndexOf('/') + 1)
	if (base.lastIndexOf('.') !== -1) {
		base = base.substring(0, base.lastIndexOf('.'))
	}
	return base
}

export function getUrlParameter(sParam) {
	const sPageURL = window.location.search.substring(1)
	const sURLVariables = sPageURL.split('&')
	for (let i = 0; i < sURLVariables.length; i++) {
		const sParameterName = sURLVariables[i].split('=')
		if (sParameterName[0] === sParam) {
			return decodeURIComponent(sParameterName[1])
		}
	}
}

/*
 * get key events
 */
export function checkKey(e) {
	e = e || window.event
	const kc = e.keyCode
	// console.log(kc)

	// key '<'
	if (kc === 60 || kc === 220) {
		e.preventDefault()
	}

	// if (e.key === 'Escape') {
	// }
}

export function reload(msg) {
	showInfo(msg)
	// eslint-disable-next-line
	new Timer(function() {
		location.reload()
	}, 5000)
}

export function slugify(text) {
	let str = String(text).toString()
	str = str.replace(/^\s+|\s+$/g, '')
	str = str.toLowerCase()

	const swaps = {
		0: ['°', '₀', '۰', '０'],
		1: ['¹', '₁', '۱', '１'],
		2: ['²', '₂', '۲', '２'],
		3: ['³', '₃', '۳', '３'],
		4: ['⁴', '₄', '۴', '٤', '４'],
		5: ['⁵', '₅', '۵', '٥', '５'],
		6: ['⁶', '₆', '۶', '٦', '６'],
		7: ['⁷', '₇', '۷', '７'],
		8: ['⁸', '₈', '۸', '８'],
		9: ['⁹', '₉', '۹', '９'],
		a: ['à', 'á', 'ả', 'ã', 'ạ', 'ă', 'ắ', 'ằ', 'ẳ', 'ẵ', 'ặ', 'â', 'ấ', 'ầ', 'ẩ', 'ẫ', 'ậ', 'ā', 'ą', 'å', 'α', 'ά', 'ἀ', 'ἁ', 'ἂ', 'ἃ', 'ἄ', 'ἅ', 'ἆ', 'ἇ', 'ᾀ', 'ᾁ', 'ᾂ', 'ᾃ', 'ᾄ', 'ᾅ', 'ᾆ', 'ᾇ', 'ὰ', 'ά', 'ᾰ', 'ᾱ', 'ᾲ', 'ᾳ', 'ᾴ', 'ᾶ', 'ᾷ', 'а', 'أ', 'အ', 'ာ', 'ါ', 'ǻ', 'ǎ', 'ª', 'ა', 'अ', 'ا', 'ａ', 'ä'],
		b: ['б', 'β', 'ب', 'ဗ', 'ბ', 'ｂ'],
		c: ['ç', 'ć', 'č', 'ĉ', 'ċ', 'ｃ'],
		d: ['ď', 'ð', 'đ', 'ƌ', 'ȡ', 'ɖ', 'ɗ', 'ᵭ', 'ᶁ', 'ᶑ', 'д', 'δ', 'د', 'ض', 'ဍ', 'ဒ', 'დ', 'ｄ'],
		e: ['é', 'è', 'ẻ', 'ẽ', 'ẹ', 'ê', 'ế', 'ề', 'ể', 'ễ', 'ệ', 'ë', 'ē', 'ę', 'ě', 'ĕ', 'ė', 'ε', 'έ', 'ἐ', 'ἑ', 'ἒ', 'ἓ', 'ἔ', 'ἕ', 'ὲ', 'έ', 'е', 'ё', 'э', 'є', 'ə', 'ဧ', 'ေ', 'ဲ', 'ე', 'ए', 'إ', 'ئ', 'ｅ'],
		f: ['ф', 'φ', 'ف', 'ƒ', 'ფ', 'ｆ'],
		g: ['ĝ', 'ğ', 'ġ', 'ģ', 'г', 'ґ', 'γ', 'ဂ', 'გ', 'گ', 'ｇ'],
		h: ['ĥ', 'ħ', 'η', 'ή', 'ح', 'ه', 'ဟ', 'ှ', 'ჰ', 'ｈ'],
		i: ['í', 'ì', 'ỉ', 'ĩ', 'ị', 'î', 'ï', 'ī', 'ĭ', 'į', 'ı', 'ι', 'ί', 'ϊ', 'ΐ', 'ἰ', 'ἱ', 'ἲ', 'ἳ', 'ἴ', 'ἵ', 'ἶ', 'ἷ', 'ὶ', 'ί', 'ῐ', 'ῑ', 'ῒ', 'ΐ', 'ῖ', 'ῗ', 'і', 'ї', 'и', 'ဣ', 'ိ', 'ီ', 'ည်', 'ǐ', 'ი', 'इ', 'ی', 'ｉ'],
		j: ['ĵ', 'ј', 'Ј', 'ჯ', 'ج', 'ｊ'],
		k: ['ķ', 'ĸ', 'к', 'κ', 'Ķ', 'ق', 'ك', 'က', 'კ', 'ქ', 'ک', 'ｋ'],
		l: ['ł', 'ľ', 'ĺ', 'ļ', 'ŀ', 'л', 'λ', 'ل', 'လ', 'ლ', 'ｌ'],
		m: ['м', 'μ', 'م', 'မ', 'მ', 'ｍ'],
		n: ['ñ', 'ń', 'ň', 'ņ', 'ŉ', 'ŋ', 'ν', 'н', 'ن', 'န', 'ნ', 'ｎ'],
		o: ['ó', 'ò', 'ỏ', 'õ', 'ọ', 'ô', 'ố', 'ồ', 'ổ', 'ỗ', 'ộ', 'ơ', 'ớ', 'ờ', 'ở', 'ỡ', 'ợ', 'ø', 'ō', 'ő', 'ŏ', 'ο', 'ὀ', 'ὁ', 'ὂ', 'ὃ', 'ὄ', 'ὅ', 'ὸ', 'ό', 'о', 'و', 'θ', 'ို', 'ǒ', 'ǿ', 'º', 'ო', 'ओ', 'ｏ', 'ö'],
		p: ['п', 'π', 'ပ', 'პ', 'پ', 'ｐ'],
		q: ['ყ', 'ｑ'],
		r: ['ŕ', 'ř', 'ŗ', 'р', 'ρ', 'ر', 'რ', 'ｒ'],
		s: ['ś', 'š', 'ş', 'с', 'σ', 'ș', 'ς', 'س', 'ص', 'စ', 'ſ', 'ს', 'ｓ'],
		t: ['ť', 'ţ', 'т', 'τ', 'ț', 'ت', 'ط', 'ဋ', 'တ', 'ŧ', 'თ', 'ტ', 'ｔ'],
		u: ['ú', 'ù', 'ủ', 'ũ', 'ụ', 'ư', 'ứ', 'ừ', 'ử', 'ữ', 'ự', 'û', 'ū', 'ů', 'ű', 'ŭ', 'ų', 'µ', 'у', 'ဉ', 'ု', 'ူ', 'ǔ', 'ǖ', 'ǘ', 'ǚ', 'ǜ', 'უ', 'उ', 'ｕ', 'ў', 'ü'],
		v: ['в', 'ვ', 'ϐ', 'ｖ'],
		w: ['ŵ', 'ω', 'ώ', 'ဝ', 'ွ', 'ｗ'],
		x: ['χ', 'ξ', 'ｘ'],
		y: ['ý', 'ỳ', 'ỷ', 'ỹ', 'ỵ', 'ÿ', 'ŷ', 'й', 'ы', 'υ', 'ϋ', 'ύ', 'ΰ', 'ي', 'ယ', 'ｙ'],
		z: ['ź', 'ž', 'ż', 'з', 'ζ', 'ز', 'ဇ', 'ზ', 'ｚ'],
		aa: ['ع', 'आ', 'آ'],
		ae: ['æ', 'ǽ'],
		ai: ['ऐ'],
		ch: ['ч', 'ჩ', 'ჭ', 'چ'],
		dj: ['ђ', 'đ'],
		dz: ['џ', 'ძ'],
		ei: ['ऍ'],
		gh: ['غ', 'ღ'],
		ii: ['ई'],
		ij: ['ĳ'],
		kh: ['х', 'خ', 'ხ'],
		lj: ['љ'],
		nj: ['њ'],
		oe: ['ö', 'œ', 'ؤ'],
		oi: ['ऑ'],
		oii: ['ऒ'],
		ps: ['ψ'],
		sh: ['ш', 'შ', 'ش'],
		shch: ['щ'],
		ss: ['ß'],
		sx: ['ŝ'],
		th: ['þ', 'ϑ', 'ث', 'ذ', 'ظ'],
		ts: ['ц', 'ც', 'წ'],
		ue: ['ü'],
		uu: ['ऊ'],
		ya: ['я'],
		yu: ['ю'],
		zh: ['ж', 'ჟ', 'ژ'],
		'(c)': ['©'],
		A: ['Á', 'À', 'Ả', 'Ã', 'Ạ', 'Ă', 'Ắ', 'Ằ', 'Ẳ', 'Ẵ', 'Ặ', 'Â', 'Ấ', 'Ầ', 'Ẩ', 'Ẫ', 'Ậ', 'Å', 'Ā', 'Ą', 'Α', 'Ά', 'Ἀ', 'Ἁ', 'Ἂ', 'Ἃ', 'Ἄ', 'Ἅ', 'Ἆ', 'Ἇ', 'ᾈ', 'ᾉ', 'ᾊ', 'ᾋ', 'ᾌ', 'ᾍ', 'ᾎ', 'ᾏ', 'Ᾰ', 'Ᾱ', 'Ὰ', 'Ά', 'ᾼ', 'А', 'Ǻ', 'Ǎ', 'Ａ', 'Ä'],
		B: ['Б', 'Β', 'ब', 'Ｂ'],
		C: ['Ç', 'Ć', 'Č', 'Ĉ', 'Ċ', 'Ｃ'],
		D: ['Ď', 'Ð', 'Đ', 'Ɖ', 'Ɗ', 'Ƌ', 'ᴅ', 'ᴆ', 'Д', 'Δ', 'Ｄ'],
		E: ['É', 'È', 'Ẻ', 'Ẽ', 'Ẹ', 'Ê', 'Ế', 'Ề', 'Ể', 'Ễ', 'Ệ', 'Ë', 'Ē', 'Ę', 'Ě', 'Ĕ', 'Ė', 'Ε', 'Έ', 'Ἐ', 'Ἑ', 'Ἒ', 'Ἓ', 'Ἔ', 'Ἕ', 'Έ', 'Ὲ', 'Е', 'Ё', 'Э', 'Є', 'Ə', 'Ｅ'],
		F: ['Ф', 'Φ', 'Ｆ'],
		G: ['Ğ', 'Ġ', 'Ģ', 'Г', 'Ґ', 'Γ', 'Ｇ'],
		H: ['Η', 'Ή', 'Ħ', 'Ｈ'],
		I: ['Í', 'Ì', 'Ỉ', 'Ĩ', 'Ị', 'Î', 'Ï', 'Ī', 'Ĭ', 'Į', 'İ', 'Ι', 'Ί', 'Ϊ', 'Ἰ', 'Ἱ', 'Ἳ', 'Ἴ', 'Ἵ', 'Ἶ', 'Ἷ', 'Ῐ', 'Ῑ', 'Ὶ', 'Ί', 'И', 'І', 'Ї', 'Ǐ', 'ϒ', 'Ｉ'],
		J: ['Ｊ'],
		K: ['К', 'Κ', 'Ｋ'],
		L: ['Ĺ', 'Ł', 'Л', 'Λ', 'Ļ', 'Ľ', 'Ŀ', 'ल', 'Ｌ'],
		M: ['М', 'Μ', 'Ｍ'],
		N: ['Ń', 'Ñ', 'Ň', 'Ņ', 'Ŋ', 'Н', 'Ν', 'Ｎ'],
		O: ['Ó', 'Ò', 'Ỏ', 'Õ', 'Ọ', 'Ô', 'Ố', 'Ồ', 'Ổ', 'Ỗ', 'Ộ', 'Ơ', 'Ớ', 'Ờ', 'Ở', 'Ỡ', 'Ợ', 'Ø', 'Ō', 'Ő', 'Ŏ', 'Ο', 'Ό', 'Ὀ', 'Ὁ', 'Ὂ', 'Ὃ', 'Ὄ', 'Ὅ', 'Ὸ', 'Ό', 'О', 'Θ', 'Ө', 'Ǒ', 'Ǿ', 'Ｏ', 'Ö'],
		P: ['П', 'Π', 'Ｐ'],
		Q: ['Ｑ'],
		R: ['Ř', 'Ŕ', 'Р', 'Ρ', 'Ŗ', 'Ｒ'],
		S: ['Ş', 'Ŝ', 'Ș', 'Š', 'Ś', 'С', 'Σ', 'Ｓ'],
		T: ['Ť', 'Ţ', 'Ŧ', 'Ț', 'Т', 'Τ', 'Ｔ'],
		U: ['Ú', 'Ù', 'Ủ', 'Ũ', 'Ụ', 'Ư', 'Ứ', 'Ừ', 'Ử', 'Ữ', 'Ự', 'Û', 'Ū', 'Ů', 'Ű', 'Ŭ', 'Ų', 'У', 'Ǔ', 'Ǖ', 'Ǘ', 'Ǚ', 'Ǜ', 'Ｕ', 'Ў', 'Ü'],
		V: ['В', 'Ｖ'],
		W: ['Ω', 'Ώ', 'Ŵ', 'Ｗ'],
		X: ['Χ', 'Ξ', 'Ｘ'],
		Y: ['Ý', 'Ỳ', 'Ỷ', 'Ỹ', 'Ỵ', 'Ÿ', 'Ῠ', 'Ῡ', 'Ὺ', 'Ύ', 'Ы', 'Й', 'Υ', 'Ϋ', 'Ŷ', 'Ｙ'],
		Z: ['Ź', 'Ž', 'Ż', 'З', 'Ζ', 'Ｚ'],
		AE: ['Æ', 'Ǽ'],
		Ch: ['Ч'],
		Dj: ['Ђ'],
		Dz: ['Џ'],
		Gx: ['Ĝ'],
		Hx: ['Ĥ'],
		Ij: ['Ĳ'],
		Jx: ['Ĵ'],
		Kh: ['Х'],
		Lj: ['Љ'],
		Nj: ['Њ'],
		Oe: ['Œ'],
		Ps: ['Ψ'],
		Sh: ['Ш'],
		Shch: ['Щ'],
		Ss: ['ẞ'],
		Th: ['Þ'],
		Ts: ['Ц'],
		Ya: ['Я'],
		Yu: ['Ю'],
		Zh: ['Ж'],
	}

	Object.keys(swaps).forEach((swap) => {
		swaps[swap].forEach(s => {
			str = str.replace(new RegExp(s, 'g'), swap)
		})
	})
	return str
		.replace(/[^a-z0-9 -]/g, '_') // remove invalid chars
		.replace(/\s+/g, '-') // collapse whitespace and replace by -
		.replace(/-+/g, '-') // collapse dashes
		.replace(/^-+/, '') // trim - from start of text
		.replace(/-+$/, '')
}
