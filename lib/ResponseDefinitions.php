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

/**
 * @psalm-type CospendAccessLevel = 0|1|2|3|4
 * @psalm-type CospendShareType = 'l'|'u'|'g'|'c'
 * @psalm-type CospendFrequency = 'n'|'d'|'w'|'b'|'s'|'m'|'y'
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
 *      type: 'u',
 *      userid: string,
 *      name: string,
 *      manually_added: bool,
 *  }
 *
 * @psalm-type CospendGroupShare = CospendBaseShare&array{
 *      type: 'g',
 *      groupid: string,
 *      name: string,
 *  }
 *
 * @psalm-type CospendCircleShare = CospendBaseShare&array{
 *     type: 'c',
 *     circleid: string,
 *     name: string,
 * }
 *
 * @psalm-type CospendPublicShare = CospendBaseShare&array{
 *     type: 'l',
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
 *      balance: array<float>,
 *      nb_bills: int,
 *      total_spent: float,
 *      nb_trashbin_bills: int,
 *      shares: CospendShare[],
 *      currencies: CospendCurrency[],
 *      categories: CospendCategoryOrPaymentMode[],
 *      paymentmodes: CospendCategoryOrPaymentMode[],
 *  }
 *
 * @psalm-type CospendPublicProjectInfo = array{
 *     id: int,
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
 * @psalm-type CospendProjectInfo = CospendPublicProjectInfo&array{
 *     userid: string,
 * }
 *
 * @psalm-type CospendPublicProjectInfoPlusExtra = CospendPublicProjectInfo&CospendExtraProjectInfo
 * @psalm-type CospendProjectInfoPlusExtra = CospendProjectInfo&CospendExtraProjectInfo
 *
 * @psalm-type CospendFullPublicProjectInfo = CospendPublicProjectInfoPlusExtra&array{
 *       myaccesslevel: int,
 *  }
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
 *
 * @psalm-type CospendProjectSettlement = array{
 *     transactions: ?array<array{to: int, amount: float, from: int}>,
 *     balances: array<string, float>,
 * }
 *
 * @psalm-type CospendProjectStatistics = array<string, mixed>
 *
 * @psalm-type CospendFederationInvite = array{
 *     id: int,
 *     userId: string,
 *     state: int,
 *     remoteProjectId: string,
 *     remoteProjectName: string,
 *     remoteServerUrl: string,
 *     inviterCloudId: string,
 *     inviterDisplayName: string,
 * }
 */
class ResponseDefinitions {
}
