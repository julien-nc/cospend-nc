<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Julien Veyssier <julien-nc@posteo.net>
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Cospend;

use OCA\Cospend\AppInfo\Application;

/**
 * @psalm-type CospendAccessLevel = Application::ACCESS_LEVEL_NONE|Application::ACCESS_LEVEL_VIEWER|Application::ACCESS_LEVEL_PARTICIPANT|Application::ACCESS_LEVEL_MAINTAINER|Application::ACCESS_LEVEL_ADMIN
 * @psalm-type CospendShareType = Application::SHARE_TYPE_PUBLIC_LINK|Application::SHARE_TYPE_USER|Application::SHARE_TYPE_GROUP|Application::SHARE_TYPE_CIRCLE
 * @psalm-type CospendFrequency = Application::FREQUENCY_NO|Application::FREQUENCY_DAILY|Application::FREQUENCY_WEEKLY|Application::FREQUENCY_BI_WEEKLY|Application::FREQUENCY_SEMI_MONTHLY|Application::FREQUENCY_MONTHLY|Application::FREQUENCY_YEARLY
 *
 * @psalm-type CospendMember = array{
 *     activated: bool,
 *     userid: ?string,
 *     name: string,
 *     id: int,
 *     weight: float,
 *     color: array{r: int, g: int, b: int},
 *     lastchanged: int,
 * }
 *
 * @psalm-type CospendBaseShare = array{
 *     id: int,
 *     accesslevel: CospendAccessLevel,
 * }
 *
 * @psalm-type CospendUserShare = CospendBaseShare&array{
 *      type: Application::SHARE_TYPE_USER,
 *      userid: string,
 *      name: string,
 *      manually_added: bool,
 *  }
 *
 * @psalm-type CospendGroupShare = CospendBaseShare&array{
 *      type: Application::SHARE_TYPE_GROUP,
 *      groupid: string,
 *      name: string,
 *  }
 *
 * @psalm-type CospendCircleShare = CospendBaseShare&array{
 *     type: Application::SHARE_TYPE_CIRCLE,
 *     circleid: string,
 *     name: string,
 * }
 *
 * @psalm-type CospendPublicShare = CospendBaseShare&array{
 *     type: Application::SHARE_TYPE_PUBLIC_LINK,
 *     token: string,
 *     label: ?string,
 *     password: ?string,
 * }
 *
 * @psalm-type CospendShare = array<CospendUserShare|CospendGroupShare|CospendCircleShare|CospendPublicShare>
 *
 * @psalm-type CospendCurrency = array{
 *     id: int,
 *     name: string,
 *     exchange_rate: float,
 *     projectid: string,
 *  }
 *
 * @psalm-type CospendCategoryOrPaymentMode = array{
 *     id: int,
 *     projectid: string,
 *     name: ?string,
 *     color: ?string,
 *     icon: ?string,
 *     order: int,
 *  }
 *
 * @psalm-type CospendCategory = CospendCategoryOrPaymentMode
 *
 * @psalm-type CospendPaymentMode = CospendCategoryOrPaymentMode&array{
 *     old_id: string,
 *  }
 *
 * @psalm-type CospendExtraProjectInfo = array{
 *      active_members: CospendMember[],
 *      members: CospendMember[],
 *      balance: array<int, float>,
 *      nb_bills: int,
 *      total_spent: float,
 *      nb_trashbin_bills: int,
 *      shares: CospendShare[],
 *      currencies: CospendCurrency[],
 *      categories: CospendCategoryOrPaymentMode[],
 *      paymentmodes: CospendCategoryOrPaymentMode[],
 *  }
 *
 * @psalm-type CospendProjectInfo = array{
 *     id: int,
 *     userid: string,
 *     name: string,
 *     email: ?string,
 *     autoexport: string,
 *     lastchanged: int,
 *     deletiondisabled: bool,
 *     categorysort: string,
 *     paymentmodesort: string,
 *     currencyname: string,
 *     archived_ts: int,
 * }
 *
 * @psalm-type CospendProjectInfoPlusExtra = CospendProjectInfo&CospendExtraProjectInfo
 *
 * @psalm-type CospendFullProjectInfo = CospendProjectInfoPlusExtra&array{
 *      myaccesslevel: int,
 * }
 *
 * @psalm-type CospendOwer = array{
 *     id: int,
 *     weight: float,
 *     name: string,
 *     activated: bool,
 * }
 *
 * @psalm-type CospendBill = array{
 *     id: int,
 *     amount: float,
 *     what: string,
 *     comment: string,
 *     timestamp: int,
 *     date: string,
 *     payer_id: int,
 *     owers: CospendOwer[],
 *     owerIds: int[],
 *     repeat: CospendFrequency,
 *     paymentmode: string,
 *     paymentmodeid: int,
 *     categoryid: int,
 *     lastchanged: int,
 *     repeatallactive: int,
 *     repeatuntil: string,
 *     repeatfreq: int,
 *     deleted: int,
 * }
 */
class ResponseDefinitions {
}
