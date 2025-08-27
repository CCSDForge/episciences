<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0"
                xmlns:php="http://php.net/xsl"
                xmlns:dc="http://purl.org/dc/elements/1.1/"
                xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/">

    <xsl:output method="html" encoding="utf-8" indent="yes"/>

    <xsl:template match="/record">

        <xsl:variable name="client_language" select="php:function('Episciences_Tools::getLocale')"/>
        <xsl:variable name="doc_language" select="metadata/oai_dc:dc/dc:language"/>
        <xsl:variable name="title">
            <xsl:choose>
                <xsl:when test="metadata/oai_dc:dc/dc:title/@xml:lang = $client_language">
                    <xsl:value-of select="metadata/oai_dc:dc/dc:title[@xml:lang = $client_language]"/>
                </xsl:when>
                <xsl:when test="metadata/oai_dc:dc/dc:title/@xml:lang = $doc_language">
                    <xsl:value-of select="metadata/oai_dc:dc/dc:title[@xml:lang = $doc_language]"/>
                </xsl:when>
                <xsl:when test="metadata/oai_dc:dc/dc:title/@xml:lang">
                    <xsl:value-of select="metadata/oai_dc:dc/dc:title[@xml:lang]"/>
                </xsl:when>
                <xsl:otherwise>
                    <xsl:value-of select="metadata/oai_dc:dc/dc:title"/>
                </xsl:otherwise>
            </xsl:choose>
        </xsl:variable>
        <xsl:variable name="docUrl" select="episciences/docURL"/>
        <xsl:variable name="docId" select="episciences/id"/>

        <div class="panel panel-default collapsable" style="margin-top: 20px">

            <div class="panel-heading">
                <h2 class="panel-title">

                    <xsl:value-of select="php:function('Episciences_Tools::decodeLatex', string($title))"/>
                    (v<xsl:value-of select="episciences/version"/>)
                </h2>
            </div>

            <div class="panel-body in">

                <p>
                    <i>
                        <div>
                            <xsl:value-of select="episciences/authorEnriched" disable-output-escaping="yes"/>
                        </div>

                        <xsl:if test="episciences/submission_date">
                            <div>
                                <xsl:value-of select="php:function('Ccsd_Tools::translate', 'Proposé par : ')"/>
                                <xsl:value-of select="episciences/submitter"/> (<xsl:value-of
                                    select="php:function('Episciences_View_Helper_Date::Date', string(episciences/submission_date))"/>)
                                <span class="label label-default pull-right"><xsl:value-of select="php:function('Ccsd_Tools::translate',string(episciences/submissionType))"/></span>
                            </div>
                        </xsl:if>
                    </i>
                </p>

                <xsl:call-template name="process-descriptions">
                    <xsl:with-param name="justify" select="'true'"/>
                </xsl:call-template>

                <hr/>

                <xsl:if test="episciences/id and episciences/id != ''">
                    <div class="small">
                        <xsl:value-of select="php:function('Ccsd_Tools::translate', 'Id : ')"/>
                        <xsl:value-of select="episciences/id"/>
                    </div>
                </xsl:if>

                <!-- licenses -->

                <xsl:choose>
                    <xsl:when test="episciences/paperLicence/text() != ''">
                        <div class="small">
                            <xsl:value-of select="php:function('Ccsd_Tools::translate', 'Licence : ')"/>
                            <a rel="noopener" target="_blank">
                                <xsl:attribute name="href">
                                    <xsl:value-of select="episciences/paperLicence/text()"/>
                                </xsl:attribute>
                                <xsl:value-of
                                        select="php:function('Ccsd_Tools::translate', string(episciences/paperLicence))"/>
                            </a>
                        </div>
                    </xsl:when>
                    <xsl:otherwise>
                        <xsl:for-each select="metadata/oai_dc:dc/dc:rights">
                            <xsl:variable name="doc_rights" select="."/>
                            <xsl:if test="not (contains($doc_rights, 'info:eu-repo/semantics/'))">
                                <div class="small">
                                    <xsl:value-of select="php:function('Ccsd_Tools::translate', 'Licence : ')"/>
                                    <a rel="noopener" target="_blank">
                                        <xsl:if test="not (contains($doc_rights, '[CC_NO]'))">
                                            <xsl:attribute name="href">
                                                <xsl:value-of select="$doc_rights"/>
                                            </xsl:attribute>
                                        </xsl:if>
                                        <xsl:value-of select="php:function('Ccsd_Tools::translate', string($doc_rights))"/>
                                    </a>
                                </div>
                            </xsl:if>
                        </xsl:for-each>
                    </xsl:otherwise>
                </xsl:choose>

                <div class="small">
                    <xsl:choose>
                        <xsl:when test="episciences/tmp/text() = '1'">
                            <xsl:variable name="docUrls" select="php:function('Episciences_Tools::buildHtmlTmpDocUrls', $docId)"/>
                            <xsl:value-of select=" $docUrls" disable-output-escaping="yes"/>
                        </xsl:when>
                        <xsl:otherwise>
                            <a target="_blank">
                                <xsl:attribute name="href">
                                    <xsl:value-of select="$docUrl"/>
                                </xsl:attribute>
                                <xsl:value-of select="$docUrl"/>
                            </a>
                        </xsl:otherwise>
                    </xsl:choose>
                </div>

                <xsl:if test="episciences/notHasHook/text() = '1' and (episciences/docURL != episciences/paperURL)">
                    <div class="small">
                        <a target="_blank">
                            <xsl:attribute name="href">
                                <xsl:value-of select="episciences/paperURL"/>
                            </xsl:attribute>
                            <xsl:value-of select="episciences/paperURL"/>
                        </a>
                    </div>
                </xsl:if>

            </div>

        </div>

    </xsl:template>

    <!-- Template for processing descriptions with conditional language prefixes -->
    <xsl:template name="process-descriptions">
        <xsl:param name="justify" select="'false'"/>
        
        <!-- Count only displayable descriptions (excluding 'International audience') -->
        <xsl:variable name="displayable_desc_count" select="count(metadata/oai_dc:dc/dc:description[normalize-space(.) != 'International audience'])"/>
        
        <xsl:for-each select="metadata/oai_dc:dc/dc:description">
            <!-- Skip descriptions with value 'International audience' -->
            <xsl:if test="normalize-space(.) != 'International audience'">
                <xsl:choose>
                    <xsl:when test="$justify = 'true'">
                        <p class="small force-word-wrap" style="text-align: justify">
                            <!-- Add lang attribute if language is specified -->
                            <xsl:if test="@xml:lang">
                                <xsl:attribute name="lang">
                                    <xsl:value-of select="@xml:lang"/>
                                </xsl:attribute>
                                <!-- Add dir attribute for RTL languages -->
                                <xsl:if test="php:function('Episciences_Tools::isRtlLanguage', string(@xml:lang))">
                                    <xsl:attribute name="dir">rtl</xsl:attribute>
                                </xsl:if>
                            </xsl:if>
                            <!-- Only add language prefix if multiple descriptions AND this one has xml:lang -->
                            <xsl:if test="$displayable_desc_count > 1 and @xml:lang">
                                <strong>[<xsl:value-of select="@xml:lang"/>] </strong>
                            </xsl:if>
                            <xsl:value-of select="php:function('Episciences_Tools::decodeLatex', string(.), true())" disable-output-escaping="yes"/>
                        </p>
                    </xsl:when>
                    <xsl:otherwise>
                        <p class="small force-word-wrap" style="">
                            <!-- Add lang attribute if language is specified -->
                            <xsl:if test="@xml:lang">
                                <xsl:attribute name="lang">
                                    <xsl:value-of select="@xml:lang"/>
                                </xsl:attribute>
                                <!-- Add dir attribute for RTL languages -->
                                <xsl:if test="php:function('Episciences_Tools::isRtlLanguage', string(@xml:lang))">
                                    <xsl:attribute name="dir">rtl</xsl:attribute>
                                </xsl:if>
                            </xsl:if>
                            <!-- Only add language prefix if multiple descriptions AND this one has xml:lang -->
                            <xsl:if test="$displayable_desc_count > 1 and @xml:lang">
                                <strong>[<xsl:value-of select="@xml:lang"/>] </strong>
                            </xsl:if>
                            <xsl:value-of select="php:function('Episciences_Tools::decodeLatex', string(.), true())" disable-output-escaping="yes"/>
                        </p>
                    </xsl:otherwise>
                </xsl:choose>
            </xsl:if>
        </xsl:for-each>
    </xsl:template>

</xsl:stylesheet> 